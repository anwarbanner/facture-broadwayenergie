<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Administration > Utilisateurs (Admin only).
 * Two roles exist: Admin (everything) and Technicien (operations, no finance / administration).
 * Safeguards: nobody deletes their own account, and the last active administrator can be neither
 * deleted, deactivated nor turned into a Technicien (the application would have no admin left).
 */
class UserController extends Controller
{
    public const ROLES = [
        'Admin' => 'Administrateur : tout l\'ERP, finance, comptabilite, utilisateurs, reglages',
        'Technicien' => 'Operations : devis, commandes, livraisons, achats, stock, contacts (sans finance ni administration)',
    ];

    public const STATUSES = ['active' => 'Actif', 'unactive' => 'Inactif'];

    private function allowedRoleNames(): array
    {
        foreach (array_keys(self::ROLES) as $name) {
            Role::firstOrCreate(['name' => $name], ['guard_name' => 'web']);
        }

        return array_keys(self::ROLES);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys(self::ROLES))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'lastLogins' => $this->lastLogins($users->pluck('id')->all()),
            'counts' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'admins' => User::role('Admin')->count(),
                'inactive' => User::where('status', '!=', 'active')->count(),
            ],
            'roles' => self::ROLES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create()
    {
        $this->allowedRoleNames();

        return view('users.create', ['user' => new User(['status' => 'active']), 'roles' => self::ROLES, 'statuses' => self::STATUSES, 'currentRole' => 'Technicien']);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_name' => [$data['role_name']],
            'status' => $data['status'],
        ]);
        $user->syncRoles([$data['role_name']]);

        return redirect()->route('users.index')->with('success', 'Utilisateur ' . $user->name . ' cree.')
            ->with('highlight', url('users/' . $user->id));
    }

    public function show($id)
    {
        $user = User::with('roles:id,name')->findOrFail($id);

        return view('users.show', [
            'user' => $user,
            'lastLogin' => $this->lastLogins([$user->id])[$user->id] ?? null,
            'activity' => ActivityLog::where('user_id', $user->id)->whereNotIn('category', ['view', 'auth_form', 'login', 'logout'])->latest('id')->take(10)->get(),
            'actionsCount' => ActivityLog::where('user_id', $user->id)->whereNotIn('category', ['view', 'auth_form', 'login', 'logout'])->count(),
            'roles' => self::ROLES,
        ]);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->allowedRoleNames();

        return view('users.edit', ['user' => $user, 'roles' => self::ROLES, 'statuses' => self::STATUSES, 'currentRole' => $user->getRoleNames()->first() ?? 'Technicien']);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate($this->rules($user->id), $this->messages());

        $losesAdmin = $user->hasRole('Admin') && ($data['role_name'] !== 'Admin' || $data['status'] !== 'active');
        if ($losesAdmin && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['role_name' => 'C\'est le dernier administrateur actif : il doit rester administrateur et actif.']);
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_name' => [$data['role_name']],
            'status' => $data['status'],
        ];
        if (!empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        }

        DB::transaction(function () use ($user, $attributes, $data) {
            $user->update($attributes);
            $user->syncRoles([$data['role_name']]);
        });

        return redirect()->route('users.show', $user)->with('success', 'Utilisateur ' . $user->name . ' mis a jour.');
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail(is_numeric($id) ? (int) $id : (int) $request->input('user_id'));

        if ($user->is(Auth::user())) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($user->hasRole('Admin') && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'C\'est le dernier administrateur actif : il ne peut pas etre supprime.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Utilisateur ' . $user->name . ' supprime.');
    }

    private function rules(?int $userId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'same:confirm-password'],
            'confirm-password' => ['nullable', 'string'],
            'role_name' => ['required', Rule::in($this->allowedRoleNames())],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ];
    }

    private function messages(): array
    {
        return [
            'password.min' => 'Le mot de passe doit contenir au moins 8 caracteres.',
            'password.same' => 'Les deux mots de passe ne sont pas identiques.',
            'email.unique' => 'Cette adresse e-mail est deja utilisee par un autre utilisateur.',
        ];
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return User::role('Admin')->where('status', 'active')->whereKeyNot($user->id)->doesntExist();
    }

    /** @return array<int, \Illuminate\Support\Carbon> user id => last successful login */
    private function lastLogins(array $ids): array
    {
        return ActivityLog::whereIn('user_id', $ids)->where('category', 'login')
            ->groupBy('user_id')->selectRaw('user_id, MAX(created_at) AS last_at')
            ->pluck('last_at', 'user_id')
            ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date))
            ->all();
    }
}
