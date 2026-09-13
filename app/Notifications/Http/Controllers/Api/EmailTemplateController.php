<?php

namespace App\Notifications\Http\Controllers\Api;

use App\Notifications\Http\Requests\SaveEmailTemplateRequest;
use App\Notifications\Http\Resources\EmailTemplateResource;
use App\Notifications\Models\EmailTemplate;
use App\Notifications\Services\EmailTemplateService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        return EmailTemplateResource::collection(EmailTemplate::query()->where('organization_id', $request->user()->organization_id)->orderBy('type')->get());
    }

    public function types()
    {
        return response()->json(['data' => collect(EmailTemplateService::DEFAULTS)->map(fn ($defaults, $type) => ['type' => $type, 'variables' => EmailTemplateService::VARIABLES[$type], ...$defaults])->values()]);
    }

    public function store(SaveEmailTemplateRequest $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $template = EmailTemplate::create([...$request->validated(), 'organization_id' => $request->user()->organization_id]);

        return (new EmailTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(Request $request, EmailTemplate $email_template)
    {
        $this->ownership($request, $email_template);

        return new EmailTemplateResource($email_template);
    }

    public function update(SaveEmailTemplateRequest $request, EmailTemplate $email_template)
    {
        $this->ownership($request, $email_template);
        $email_template->update($request->validated());

        return new EmailTemplateResource($email_template);
    }

    public function destroy(Request $request, EmailTemplate $email_template)
    {
        $this->ownership($request, $email_template);
        $email_template->delete();

        return response()->noContent();
    }

    private function ownership(Request $request, EmailTemplate $template): void
    {
        abort_unless((int) $template->organization_id === (int) $request->user()->organization_id, 404);
    }
}
