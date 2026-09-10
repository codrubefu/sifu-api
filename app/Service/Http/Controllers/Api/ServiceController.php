<?php

namespace App\Service\Http\Controllers\Api;

use App\Service\Http\Requests\StoreServiceRequest;
use App\Service\Http\Requests\UpdateServiceRequest;
use App\Service\Http\Resources\ServiceResource;
use App\Service\Models\Service;
use App\Payments\Models\Payment;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Service\Services\PaymentNoteService;
use App\Service\Services\ServiceDocumentSequenceService;
use App\Service\Services\ServiceInvoiceService;
use App\Users\Http\Controllers\Controller;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceLifecycleService $lifecycle,
        private readonly PaymentNoteService $paymentNotes,
        private readonly ServiceDocumentSequenceService $documentSequences,
        private readonly ServiceInvoiceService $invoices,
        private readonly OrganizationAccessService $organizationAccess,
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $services = Service::query()
            ->withCount('users')
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->when($request->boolean('only_trashed'), fn ($query) => $query->onlyTrashed())
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $service = DB::transaction(function () use ($data, $userIds): Service {
            $service = Service::query()->create($data);
            $service->users()->sync($userIds);

            return $service;
        });

        return (new ServiceResource($service->load('users')->loadCount('users')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service->load('users')->loadCount('users'));
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? null;
        unset($data['user_ids']);

        DB::transaction(function () use ($service, $data, $userIds): void {
            $service->update($data);

            if ($userIds !== null) {
                $currentUserIds = $service->users()->pluck('users.id')->all();
                $service->users()->detach(array_diff($currentUserIds, $userIds));
                $service->users()->syncWithoutDetaching($userIds);
            }
        });

        return new ServiceResource($service->load('users')->loadCount('users'));
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($error = $this->organizationAccess->deleteBlockedByManyToManyResponse($service, ['users'])) {
            return response()->json($error, 422);
        }

        $service->delete();

        return response()->json(status: 204);
    }

    public function restore(int $service): ServiceResource
    {
        $service = Service::onlyTrashed()->findOrFail($service);
        $service->restore();

        return new ServiceResource($service->load('users')->loadCount('users'));
    }

    public function toggleActive(Service $service): ServiceResource
    {
        $service->update([
            'is_active' => ! $service->is_active,
        ]);

        return new ServiceResource($service->load('users')->loadCount('users'));
    }

    public function activate(Request $request, ServiceUser $assignment): JsonResponse
    {
        $data = $request->validate(['payment_id' => ['nullable', 'integer', 'exists:payments,id']]);
        $payment = isset($data['payment_id']) ? Payment::query()->findOrFail($data['payment_id']) : null;
        $assignment = $this->lifecycle->activate($assignment, $payment);

        return response()->json(['data' => $assignment->load(['service', 'user', 'activationPayment'])]);
    }

    public function suspend(Request $request, ServiceUser $assignment): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'resume_at' => ['nullable', 'date', 'after:now'],
        ]);
        $assignment = $this->lifecycle->suspend(
            $assignment,
            $data['reason'],
            isset($data['resume_at']) ? now()->parse($data['resume_at']) : null,
        );

        return response()->json(['data' => $assignment]);
    }

    public function resume(ServiceUser $assignment): JsonResponse
    {
        return response()->json(['data' => $this->lifecycle->resume($assignment)]);
    }

    public function consume(ServiceUser $assignment): JsonResponse
    {
        return response()->json(['data' => $this->lifecycle->consumeAccess($assignment)]);
    }

    public function paymentNote(Request $request, ServiceUser $assignment): Response
    {
        $assignment->loadMissing(['service']);
        abort_unless((int) $assignment->service->organization_id === (int) $request->user()->organization_id, 404);

        return $this->paymentNotes->download($assignment);
    }

    public function generateInvoice(Request $request, ServiceUser $assignment): JsonResponse
    {
        $assignment->loadMissing(['service']);
        abort_unless((int) $assignment->service->organization_id === (int) $request->user()->organization_id, 404);

        $assignment = DB::transaction(function () use ($assignment): ServiceUser {
            $lockedAssignment = ServiceUser::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAssignment->loadMissing(['service']);

            if (blank($lockedAssignment->invoice_number)) {
                $lockedAssignment->forceFill([
                    'invoice_number' => $this->documentSequences->nextInvoice((int) $lockedAssignment->service->organization_id),
                ])->save();
            }

            return $lockedAssignment;
        });

        return response()->json(['data' => $assignment->load(['service', 'user', 'activationPayment'])]);
    }

    public function invoice(Request $request, ServiceUser $assignment, string $format = 'pdf'): Response
    {
        $assignment->loadMissing(['service']);
        abort_unless((int) $assignment->service->organization_id === (int) $request->user()->organization_id, 404);
        abort_if(blank($assignment->invoice_number), 404);

        return $format === 'xml'
            ? $this->invoices->xmlDownload($assignment)
            : $this->invoices->download($assignment);
    }
}
