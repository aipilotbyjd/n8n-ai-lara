<?php

namespace Database\Seeders;

use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class CredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();
        $users = User::all();

        if ($organizations->isEmpty() || $users->isEmpty()) {
            return;
        }

        $this->createSampleCredentials($organizations, $users);
    }

    private function createSampleCredentials($organizations, $users): void
    {
        $sampleCredentials = [
            [
                'name' => 'GitHub API Token',
                'type' => 'oauth2',
                'data' => json_encode([
                    'client_id' => 'gh_client_' . rand(1000, 9999),
                    'client_secret' => 'gh_secret_' . rand(100000, 999999),
                    'access_token' => 'gh_access_' . rand(100000, 999999),
                    'refresh_token' => 'gh_refresh_' . rand(100000, 999999),
                ]),
                'expires_at' => now()->addDays(30),
            ],
            [
                'name' => 'Slack Bot Token',
                'type' => 'api_key',
                'data' => json_encode([
                    'api_key' => 'xoxb-' . rand(1000000000, 9999999999) . '-' . rand(1000000000, 9999999999) . '-' . strtoupper(substr(md5(rand()), 0, 24)),
                    'team_id' => 'T' . rand(1000000, 9999999),
                    'bot_id' => 'B' . rand(1000000, 9999999),
                ]),
            ],
            [
                'name' => 'Stripe API Keys',
                'type' => 'api_key',
                'data' => json_encode([
                    'publishable_key' => 'pk_test_' . rand(1000000000, 9999999999),
                    'secret_key' => 'sk_test_' . rand(1000000000, 9999999999),
                    'webhook_secret' => 'whsec_' . rand(1000000000, 9999999999),
                ]),
            ],
            [
                'name' => 'SendGrid SMTP',
                'type' => 'smtp',
                'data' => json_encode([
                    'host' => 'smtp.sendgrid.net',
                    'port' => 587,
                    'username' => 'apikey',
                    'password' => 'SG.' . strtoupper(substr(md5(rand()), 0, 32)) . '.' . strtoupper(substr(md5(rand()), 0, 32)),
                    'encryption' => 'tls',
                ]),
            ],
            [
                'name' => 'AWS S3 Credentials',
                'type' => 'aws',
                'data' => json_encode([
                    'access_key_id' => 'AKIA' . strtoupper(substr(md5(rand()), 0, 16)),
                    'secret_access_key' => strtoupper(substr(md5(rand()), 0, 40)),
                    'region' => 'us-east-1',
                    'bucket' => 'company-files-' . rand(1000, 9999),
                ]),
            ],
            [
                'name' => 'Twilio SMS API',
                'type' => 'api_key',
                'data' => json_encode([
                    'account_sid' => 'AC' . rand(1000000000, 9999999999),
                    'auth_token' => strtoupper(substr(md5(rand()), 0, 32)),
                    'phone_number' => '+1' . rand(1000000000, 9999999999),
                ]),
            ],
            [
                'name' => 'Google Analytics API',
                'type' => 'oauth2',
                'data' => json_encode([
                    'client_id' => rand(100000000000, 999999999999) . '.apps.googleusercontent.com',
                    'client_secret' => strtoupper(substr(md5(rand()), 0, 24)),
                    'access_token' => 'ya29.' . strtoupper(substr(md5(rand()), 0, 100)),
                    'refresh_token' => strtoupper(substr(md5(rand()), 0, 45)),
                ]),
                'expires_at' => now()->addHours(1),
            ],
            [
                'name' => 'Database Connection',
                'type' => 'database',
                'data' => json_encode([
                    'host' => 'db.example.com',
                    'port' => 3306,
                    'database' => 'production_db',
                    'username' => 'app_user',
                    'password' => 'secure_password_' . rand(1000, 9999),
                    'charset' => 'utf8mb4',
                ]),
            ],
        ];

        foreach ($organizations as $organization) {
            // Create 2-4 credentials per organization
            $credentialCount = rand(2, 4);
            $selectedCredentials = array_rand($sampleCredentials, $credentialCount);

            if (!is_array($selectedCredentials)) {
                $selectedCredentials = [$selectedCredentials];
            }

            foreach ($selectedCredentials as $index) {
                $credentialData = $sampleCredentials[$index];

                Credential::create([
                    'name' => $credentialData['name'] . ' - ' . $organization->name,
                    'type' => $credentialData['type'],
                    'organization_id' => $organization->id,
                    'user_id' => $organization->owner_id,
                    'data' => Crypt::encryptString($credentialData['data']),
                    'is_shared' => rand(0, 1) ? true : false,
                    'expires_at' => $credentialData['expires_at'] ?? null,
                    'last_used_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                    'usage_count' => rand(0, 100),
                ]);
            }
        }
    }
}
