<?php

namespace App\Services;

use App\Models\SystemSetting;

class IdentityService
{
    public function entraEnabled(): bool
    {
        return SystemSetting::getValue('sso_entra_enabled', '0') === '1'
            && filled($this->entraClientId())
            && filled($this->entraTenant())
            && filled($this->entraClientSecret());
    }

    public function entraTenant(): string
    {
        return trim((string) SystemSetting::getValue('sso_entra_tenant', ''));
    }

    public function entraClientId(): string
    {
        return trim((string) SystemSetting::getValue('sso_entra_client_id', ''));
    }

    public function entraClientSecret(): string
    {
        return trim((string) SystemSetting::getValue('sso_entra_client_secret', ''));
    }

    public function entraRedirectUri(): string
    {
        return route('auth.entra.callback');
    }

    public function authorizeUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->entraClientId(),
            'response_type' => 'code',
            'redirect_uri' => $this->entraRedirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
        ]);

        return 'https://login.microsoftonline.com/'.$this->entraTenant().'/oauth2/v2.0/authorize?'.$query;
    }

    public function tokenEndpoint(): string
    {
        return 'https://login.microsoftonline.com/'.$this->entraTenant().'/oauth2/v2.0/token';
    }

    /**
     * @return array{enabled: bool, tenant: string, client_id: string, client_secret: string, redirect: string}
     */
    public function adminState(): array
    {
        return [
            'enabled' => SystemSetting::getValue('sso_entra_enabled', '0') === '1',
            'tenant' => $this->entraTenant(),
            'client_id' => $this->entraClientId(),
            'client_secret' => $this->entraClientSecret(),
            'redirect' => $this->entraRedirectUri(),
        ];
    }
}
