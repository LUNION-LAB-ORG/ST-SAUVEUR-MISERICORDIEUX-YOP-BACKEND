<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donation\StoreRequest;
use App\Http\Requests\Donation\UpdateRequest;
use App\Http\Resources\DonationResource;
use App\Support\CsvExport;
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
        // Auto-expiration défensive : les dons Wave restés en "pending" depuis
        // plus d'1h sans webhook de confirmation/échec sont considérés comme
        // abandonnés et marqués "failed". Évite qu'ils polluent les stats ou
        // qu'ils s'affichent comme "à valider" indéfiniment si Wave ne
        // renvoie pas d'event expired.
        \App\Models\Donation::query()
            ->where('payment_status', 'pending')
            ->where('paymethod', 'wave')
            ->where('created_at', '<', now()->subHour())
            ->update(['payment_status' => 'failed']);

        $query = $this->filteredQuery($request);

        // Somme des dons réussis parmi les lignes filtrées
        $totalAmount = (int) (clone $query)->where('payment_status', 'succeeded')->sum('amount');

        $donations = $query
            ->orderBy($request->input('sort_by', 'id'), $request->input('sort_dir', 'desc'))
            ->paginate((int) $request->input('per_page', 15));

        return DonationResource::collection($donations)->additional(['meta' => ['total_amount' => $totalAmount]]);
    }

    /**
     * Export CSV des dons (admin, trésorier, secrétariat). Mêmes filtres que la liste.
     */
    public function export(Request $request)
    {
        $methods = ['wave' => 'Wave', 'especes' => 'Espèces', 'cash' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement'];

        $rows = $this->filteredQuery($request)->orderBy('donation_at')->orderBy('id')->get()
            ->map(fn (\App\Models\Donation $d) => [
                CsvExport::date($d->donation_at),
                $d->donator,
                $d->email,
                $d->phone,
                $d->donation_type === 'nature' ? 'En nature' : 'Monétaire',
                $d->project,
                (int) $d->amount,
                $methods[$d->paymethod] ?? $d->paymethod,
                CsvExport::paymentStatus($d->payment_status ?? 'succeeded'),
                $d->paytransaction,
                $d->display_name ? 'Oui' : 'Non',
                $d->description,
            ]);

        return CsvExport::download(
            'dons-' . now()->format('Y-m-d') . '.csv',
            ['Date', 'Donateur', 'E-mail', 'Téléphone', 'Type', 'Projet', 'Montant (FCFA)', 'Moyen de paiement', 'Statut', 'Référence de transaction', 'Nom affiché parmi les bienfaiteurs', 'Description'],
            $rows
        );
    }

    /**
     * Filtres : ?status=succeeded|pending|failed, ?method= (ou paymethod), ?project=, ?donator=, ?from=&to=
     */
    private function filteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\Donation::query();

        if ($request->filled('status')) {
            $query->where('payment_status', $request->input('status'));
        }
        if ($request->filled('method') || $request->filled('paymethod')) {
            $query->where('paymethod', $request->input('method', $request->input('paymethod')));
        }
        if ($request->filled('project')) {
            $query->where('project', $request->input('project'));
        }
        if ($request->filled('donator')) {
            $query->where('donator', 'LIKE', '%' . $request->input('donator') . '%');
        }
        if ($request->filled('from')) {
            $query->whereDate('donation_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('donation_at', '<=', $request->input('to'));
        }

        return $query;
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
            'display_name' => 'nullable|boolean',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:30',
            'amount'      => 'required|numeric|min:100',
            'project'     => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $donation = \App\Models\Donation::create([
            'donator'        => $validated['donator'],
            'display_name'   => (bool) ($validated['display_name'] ?? false),
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
