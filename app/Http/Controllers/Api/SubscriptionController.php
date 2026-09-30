<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreRequest;
use App\Http\Resources\WhatsappSubscriberResource;
use App\Models\WhatsappSubscriber;
use App\Repositories\Contracts\WhatsappSubscriberRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionController extends Controller
{
    protected WhatsappSubscriberRepositoryInterface $repo;

    public function __construct(WhatsappSubscriberRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Abonnement public aux diffusions WhatsApp (consentement obligatoire).
     * Un numéro déjà désabonné est réabonné.
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $lists = array_values(array_unique($data['lists'] ?? WhatsappSubscriber::DEFAULT_LISTS));

        $subscriber = $this->repo->query()->where('phone', $data['phone'])->first();

        $source = $data['source'] ?? null;

        if ($subscriber) {
            $subscriber->update([
                'lists'           => $lists,
                'consented_at'    => now(),
                'unsubscribed_at' => null,
            ] + ($source ? ['source' => $source] : []));
        } else {
            $subscriber = $this->repo->create([
                'phone'        => $data['phone'],
                'lists'        => $lists,
                'consented_at' => now(),
                'source'       => $source,
            ]);
        }

        return (new WhatsappSubscriberResource($subscriber->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Désabonnement public. Body : { phone }
     */
    public function destroy(Request $request)
    {
        $request->merge(['phone' => WhatsappSubscriber::normalizePhone($request->input('phone'))]);
        $request->validate(['phone' => 'required|string|min:8|max:20']);

        $subscriber = $this->repo->query()->where('phone', $request->input('phone'))->first();
        if (!$subscriber) {
            return response()->json(['message' => 'Numéro non abonné.'], 404);
        }

        if (!$subscriber->unsubscribed_at) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return new WhatsappSubscriberResource($subscriber->fresh());
    }

    /**
     * Liste paginée des abonnés (admin). Filtres : ?status=active|unsubscribed, ?phone=
     */
    public function index(Request $request)
    {
        $subscribers = $this->filteredQuery($request)
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15));

        return WhatsappSubscriberResource::collection($subscribers);
    }

    /**
     * Export CSV des abonnés (admin) : phone, lists, consented_at, unsubscribed_at.
     */
    public function export(Request $request)
    {
        $subscribers = $this->filteredQuery($request)->orderBy('id')->get();
        $filename = 'abonnes-whatsapp-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($subscribers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['phone', 'lists', 'consented_at', 'unsubscribed_at']);

            foreach ($subscribers as $s) {
                fputcsv($out, [
                    $s->phone,
                    implode(',', $s->lists ?? []),
                    optional($s->consented_at)->toDateTimeString(),
                    optional($s->unsubscribed_at)->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Statistiques (admin) : abonnés actifs par liste, désabonnements des 30 derniers jours.
     */
    public function stats()
    {
        $lists = array_fill_keys(WhatsappSubscriber::DEFAULT_LISTS, 0);

        $this->repo->query()->whereNull('unsubscribed_at')->select(['id', 'lists'])
            ->chunkById(500, function ($chunk) use (&$lists) {
                foreach ($chunk as $subscriber) {
                    foreach (array_unique($subscriber->lists ?? []) as $list) {
                        $lists[$list] = ($lists[$list] ?? 0) + 1;
                    }
                }
            });

        return response()->json(['data' => [
            'lists'            => $lists,
            'unsubscribed_30d' => $this->repo->query()->where('unsubscribed_at', '>=', now()->subDays(30))->count(),
        ]]);
    }

    private function filteredQuery(Request $request)
    {
        $query = $this->repo->query();

        if ($request->input('status') === 'active') {
            $query->whereNull('unsubscribed_at');
        } elseif ($request->input('status') === 'unsubscribed') {
            $query->whereNotNull('unsubscribed_at');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'LIKE', '%' . WhatsappSubscriber::normalizePhone($request->input('phone')) . '%');
        }

        return $query;
    }
}
