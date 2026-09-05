<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email : The email address of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant admin privileges to an existing user by email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found for email: {$email}");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
