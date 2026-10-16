<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Public self-registration always creates a low-privilege "Technicien"
     * account. The role is never taken from user input here — promoting a
     * user to Admin is only possible for an authenticated Admin via
     * UserController.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $role = Role::firstOrCreate(['name' => 'Technicien'], ['guard_name' => 'web']);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_name' => [$role->name],
            // Self-registered accounts wait for an Admin to activate them (Utilisateurs > Modifier > actif).
            'status' => 'unactive',
        ]);

        $user->assignRole($role->name);

        event(new Registered($user));

        return redirect()->route('login')
            ->with('registered', 'Compte cree. Un administrateur doit l\'activer avant votre premiere connexion.');
    }
}
