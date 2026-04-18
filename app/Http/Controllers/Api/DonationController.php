<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donation\StoreRequest;
use App\Http\Requests\Donation\UpdateRequest;
use App\Http\Resources\DonationResource;
use App\Repositories\Contracts\DonationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DonationController extends Controller
{
    protected DonationRepositoryInterface $repo;

    public function __construct(DonationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * List donations (paginated)
     */
    public function index(Request $request)
    {
        $conditions = [];

        // Filters
        if ($request->filled('project')) {
            $conditions[] = ['project', '=', $request->project];
        }

        if ($request->filled('donator')) {
            $conditions[] = ['donator', 'LIKE', "%" . $request->donator . "%"];
        }

        if ($request->filled('paymethod')) {
            $conditions[] = ['paymethod', '=', $request->paymethod];
        }

        if ($request->filled('from')) {
            $conditions[] = ['donation_at', '>=', $request->from];
        }

        if ($request->filled('to')) {
            $conditions[] = ['donation_at', '<=', $request->to];
        }

        $donations = $this->repo->paginate(
            with: [],
            page: (int) $request->input('per_page', 15),
            conditions: $conditions,
            skip: (int) $request->input('skip', 0),
            orderBy: $request->input('sort_by', 'id'),
            direction: $request->input('sort_dir', 'desc'),
        );

        return DonationResource::collection($donations);
    }

    /**
     * Store a new donation (admin, dashboard)
     */
    public function store(StoreRequest $request)
    {
        $donation = $this->repo->create($request->validated());

        try { \App\Services\NotificationService::forDonation($donation); } catch (\Throwable $e) {}

        return (new DonationResource($donation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Enregistrer un don soumis depuis le site public (paroisse en espèces).
     *
     * Les dons Wave passent par WaveCheckoutController::createSession qui
     * crée la Donation avec payment_status='pending' puis la met à 'succeeded'
     * au webhook. Cet endpoint couvre uniquement le cas "paiement à la
     * paroisse" : on enregistre le don avec payment_status='pending' ;
     * le curé valide (passe à 'succeeded') depuis le dashboard après
     * réception effective des espèces.
     */
    public function publicStore(Request $request)
    {
        $validated = $request->validate([
            'donator'     => 'required|string|max:100',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:30',
            'amount'      => 'required|numeric|min:100',
            'project'     => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $donation = \App\Models\Donation::create([
            'donator'        => $validated['donator'],
            'email'          => $validated['email'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'donation_type'  => 'monetaire',
            'amount'         => $validated['amount'],
            'project'        => $validated['project'],
            'paymethod'      => 'especes',
            'paytransaction' => null,
            'payment_status' => 'pending',
            'description'    => $validated['description'] ?? null,
            'donation_at'    => now(),
        ]);

        try { \App\Services\NotificationService::forDonation($donation); } catch (\Throwable $e) {}

        return (new DonationResource($donation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display donation
     */
    public function show(string $id)
    {
        return new DonationResource($this->repo->find($id));
    }

    /**
     * Update donation
     */
    public function update(UpdateRequest $request, string $id)
    {
        $donation = $this->repo->update($id, $request->validated());

        return new DonationResource($donation);
    }

    /**
     * Soft delete donation
     */
    public function destroy(string $id)
    {
        $this->repo->delete($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Donation supprimée'
        ], Response::HTTP_NO_CONTENT);
    }
}
