<?php

namespace App\Http\Controllers;

use App\Models\User;

/**
 * Administration > Roles (Admin only): what each role can do, and who has it.
 *
 * Access rights depend on the role only (Admin, Technicien: gate manage-finance and the admin middleware);
 * the Spatie permission table is empty. Creating, renaming or deleting roles had no effect (a new role could
 * not be given to anyone) or could lock the application (deleting "Admin"), so roles are fixed.
 */
class RoleController extends Controller
{
    /** Rights matrix: [area, what it covers, Admin, Technicien] */
    public const MATRIX = [
        ['Ventes', 'Devis, commandes, livraisons, factures (brouillons)', true, true],
        ['Achats', 'Demandes d\'achat, commandes, receptions, factures fournisseur (brouillons)', true, true],
        ['Stock et catalogue', 'Produits, services, categories, mouvements de stock', true, true],
        ['Contacts', 'Clients et fournisseurs', true, true],
        ['Documents', 'PDF, Excel, import de lignes depuis Excel', true, true],
        ['Validation des factures', 'Numero definitif, avoirs, annulations', true, false],
        ['Paiements', 'Encaissements, decaissements, remboursements', true, false],
        ['Releves et comptabilite', 'Releves clients / fournisseurs, journaux, TVA, tresorerie, ecritures', true, false],
        ['Administration', 'Utilisateurs, journal d\'activite, signature, import Excel en masse', true, false],
    ];

    public function index()
    {
        $users = User::with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email', 'status']);

        return view('roles.index', [
            'matrix' => self::MATRIX,
            'roles' => [
                'Admin' => ['label' => 'Administrateur', 'description' => UserController::ROLES['Admin'], 'icon' => 'fe-shield', 'users' => $users->filter(fn ($u) => $u->hasRole('Admin'))->values()],
                'Technicien' => ['label' => 'Technicien', 'description' => UserController::ROLES['Technicien'], 'icon' => 'fe-briefcase', 'users' => $users->filter(fn ($u) => $u->hasRole('Technicien'))->values()],
            ],
        ]);
    }

    public function show($id)
    {
        return redirect()->route('roles.index');
    }

    public function create()
    {
        return $this->fixed();
    }

    public function store()
    {
        return $this->fixed();
    }

    public function edit($id)
    {
        return $this->fixed();
    }

    public function update($id)
    {
        return $this->fixed();
    }

    public function destroy($id)
    {
        return $this->fixed();
    }

    private function fixed()
    {
        return redirect()->route('roles.index')
            ->with('warning', 'Les roles sont fixes (Administrateur et Technicien) : pour changer les droits d\'une personne, modifiez son role dans Utilisateurs.');
    }
}
