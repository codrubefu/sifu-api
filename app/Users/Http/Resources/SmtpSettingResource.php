<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmtpSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'has_password' => filled($this->password),
            'encryption' => $this->encryption,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
