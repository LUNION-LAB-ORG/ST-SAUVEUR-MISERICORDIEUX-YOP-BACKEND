<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder du compte administrateur initial.
 *
 * Crée (ou met à jour) UN seul compte admin pour permettre la première
 * connexion au dashboard sur un VPS vierge.
 *
 * Configurable via les variables d'environnement :
 *   - ADMIN_EMAIL      (def: admin@saintsauveur.local)
 *   - ADMIN_PASSWORD   (def: ChangeMe!2026  → À CHANGER IMMÉDIATEMENT en prod)
 *   - ADMIN_FULLNAME   (def: Administrateur)
 *   - ADMIN_PHONE      (def: null)
 *
 * Idempotent : peut être rejoué sans dupliquer le compte.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@saintsauveur.local');
        $password = env('ADMIN_PASSWORD', 'ChangeMe!2026');
        $fullname = env('ADMIN_FULLNAME', 'Administrateur');
        $phone    = env('ADMIN_PHONE');

        User::updateOrCreate(
            ['email' => $email],
            [
                'fullname' => $fullname,
                'phone'    => $phone,
                'password' => Hash::make($password),
                'role'     => 'admin',
                'status'   => 'active',
            ],
        );

        $this->command?->info("Compte admin prêt : {$email}");
        if ($password === 'ChangeMe!2026') {
            $this->command?->warn('⚠  Mot de passe par défaut utilisé. À changer immédiatement via le dashboard.');
        }
    }
}
