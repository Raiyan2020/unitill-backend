<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UniversityController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $query = University::query()->with('domains')->orderBy('sort')->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhereHas('domains', function ($dq) use ($search) {
                        $dq->where('domain', 'like', "%{$search}%");
                    });
            });
        }

        $universities = $query->paginate($perPage)->through(function (University $university) {
            return $this->transform($university);
        });

        return sendResponse($universities, 'Universities fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $university = University::create([
            'name' => $data['name'],
            'country_code' => strtoupper($data['country_code'] ?? 'US'),
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => $data['status'],
            'sort' => $data['sort'] ?? 0,
        ]);

        $this->syncDomains($university, $data['domains'] ?? [], null);

        return sendResponse($this->transform($university->fresh('domains')), 'University created');
    }

    public function update(Request $request, int $id)
    {
        $university = University::with('domains')->find($id);

        if (! $university) {
            return sendError('University not found', [], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id, true));

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $university->update(array_filter([
            'name' => $data['name'] ?? null,
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => $data['status'] ?? null,
            'sort' => $data['sort'] ?? null,
        ], fn ($value) => $value !== null));

        if (array_key_exists('domains', $data)) {
            $this->syncDomains($university, $data['domains'] ?? [], $id);
        }

        return sendResponse($this->transform($university->fresh('domains')), 'University updated');
    }

    public function destroy(int $id)
    {
        $university = University::find($id);

        if (! $university) {
            return sendError('University not found', [], 404);
        }

        $university->delete();

        return sendResponse([], 'University deleted');
    }

    private function rules(?int $id = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'status' => [$required, Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', 'integer', 'min:0'],
            'domains' => ['sometimes', 'array'],
            'domains.*' => ['string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
        ];
    }

    /**
     * Replace the university's domains with the provided list (deduped, lowercased),
     * skipping any domain already owned by a different university.
     */
    private function syncDomains(University $university, array $domains, ?int $currentId): void
    {
        $clean = collect($domains)
            ->map(fn ($d) => strtolower(trim((string) $d)))
            ->filter()
            ->unique()
            ->values();

        // Drop domains that belong to another university to respect the unique constraint.
        $available = $clean->reject(function ($domain) use ($university) {
            return $university->domains()
                ->getModel()
                ->newQuery()
                ->where('domain', $domain)
                ->where('university_id', '!=', $university->id)
                ->exists();
        })->values();

        $university->domains()->whereNotIn('domain', $available)->delete();

        foreach ($available as $domain) {
            $university->domains()->updateOrCreate(
                ['domain' => $domain],
                ['status' => 'active']
            );
        }
    }

    private function transform(University $university): array
    {
        return [
            'id' => $university->id,
            'name' => $university->name,
            'country_code' => $university->country_code,
            'state' => $university->state,
            'city' => $university->city,
            'status' => $university->status,
            'sort' => (int) $university->sort,
            'domains' => $university->domains
                ->sortBy('domain')
                ->pluck('domain')
                ->values()
                ->all(),
        ];
    }
}
