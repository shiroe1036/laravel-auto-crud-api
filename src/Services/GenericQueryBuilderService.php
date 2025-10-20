<?php

namespace FivoTech\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class GenericQueryBuilderService
{
    protected ?Request $request;
    protected Model|string|null $model;
    protected array $config;
    /**
     * Relationship pagination constraints mapped by relationship name.
     *
     * @var array<string, array>
     */
    protected array $relationshipPaginations = [];

    public function __construct(?Request $request = null, Model|string|null $model = null)
    {
        $this->request = $request ?? \request();
        $this->model = $model;
        $this->config = \config('auto-crud.query_builder', []);
    }

    /**
     * Set the model for the query builder
     */
    public function setModel(Model|string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the request for the query builder
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Secure JSON decoding with error handling.
     */
    private function decodeJsonQuery(string $key, $default = null)
    {
        $str = $this->request->query($key);
        if (!$str) return $default;

        $decoded = json_decode($str, true, $this->config['max_json_depth'] ?? 10);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Parameter $key invalid: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Apply filters on relationships (whereHas).
     */
    private function buildFiltersRelationship(Builder $query, array $filtersByRelationship): Builder
    {
        foreach ($filtersByRelationship as $filterRelationship) {
            $relation = $filterRelationship['relationship'];
            $filters = $filterRelationship['filters'] ?? [];
            $orFilters = $filterRelationship['orFilters'] ?? [];

            $query->whereHas($relation, function ($q) use ($filters, $orFilters) {
                $this->buildCombinedFiltersQuery($q, $filters, $orFilters);
            });
        }
        return $query;
    }

    /**
     * Apply one or multiple whereIn on the query.
     *
     * Supports two formats:
     *
     * 1. Simple format (legacy):
     *    filtersIn: {"field": "employee_id", "values": [1,2,3]}
     *
     * 2. Multiple format (new):
     *    filtersIn: [
     *      {"field": "employee_id", "values": [1,2,3]},
     *      {"field": "tag_id", "values": ["a","b","c"]}
     *    ]
     */
    private function buildWhereInQuery(Builder|Relation $query, array $whereInData): Builder|Relation
    {
        // Support for single whereIn (legacy format)
        if (isset($whereInData['field']) && isset($whereInData['values'])) {
            if (!empty($whereInData['field']) && !empty($whereInData['values'])) {
                $query->whereIn($whereInData['field'], $whereInData['values']);
            }
        }
        // Support for multiple whereIn (new format)
        else {
            foreach ($whereInData as $whereIn) {
                if (is_array($whereIn) && !empty($whereIn['field']) && !empty($whereIn['values'])) {
                    $query->whereIn($whereIn['field'], $whereIn['values']);
                }
            }
        }
        return $query;
    }

    /**
     * Apply orderBy or orderByDesc.
     */
    private function buildOrderQuery(Builder|Relation $query, array $order): Builder|Relation
    {
        if (!empty($order['field'])) {
            $direction = ($order['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($order['field'], $direction);
        }
        return $query;
    }

    /**
     * Apply select on the query.
     */
    private function buildSelectQuery(Builder|Relation $query, array $select): Builder|Relation
    {
        if (!empty($select)) {
            $query->select($select);
        }
        return $query;
    }

    /**
     * Apply groupBy on the query.
     */
    private function buildGroupByQuery(Builder|Relation $query, array $groupBy): Builder|Relation
    {
        if (!empty($groupBy)) {
            $query->groupBy($groupBy);
        }
        return $query;
    }

    /**
     * Apply relationships with custom filters.
     */
    private function buildRelationship(Builder $query, array $relationships): Builder
    {
        $with = [];
        $this->relationshipPaginations = [];
        foreach ($relationships as $rel) {
            $key = $rel['key'] ?? null;
            if (!$key) {
                continue;
            }

            $queryConfig = $rel['query'] ?? [];
            $paginateConfig = $queryConfig['paginate'] ?? null;

            if ($paginateConfig) {
                $this->relationshipPaginations[$key] = $queryConfig;
                continue;
            }

            if (!empty($queryConfig)) {
                $with[$key] = function ($q) use ($queryConfig) {
                    $this->applyRelationshipQueryOptions($q, $queryConfig);
                };
                continue;
            }

            $with[] = $key;
        }
        if (!empty($with)) {
            $query->with($with);
        }
        return $query;
    }

    /**
     * Apply configured options on a relationship query builder.
     */
    private function applyRelationshipQueryOptions(Builder|Relation $query, array $options): void
    {
        $filters = $options['filters'] ?? [];
        $orFilters = $options['orFilters'] ?? [];
        $filtersIn = $options['filtersIn'] ?? null;
        $order = $options['order'] ?? null;
        $groupBy = $options['groupBy'] ?? null;
        $select = $options['select'] ?? null;

        $this->buildCombinedFiltersQuery($query, $filters, $orFilters);
        if ($filtersIn) {
            $this->buildWhereInQuery($query, $filtersIn);
        }
        if ($order) {
            $this->buildOrderQuery($query, $order);
        }
        if ($groupBy) {
            $this->buildGroupByQuery($query, $groupBy);
        }
        if ($select) {
            $this->buildSelectQuery($query, $select);
        }
    }

    /**
     * Apply relationship pagination on resulting models when requested.
     */
    private function applyRelationshipPaginations(mixed $result): mixed
    {
        if (empty($this->relationshipPaginations)) {
            return $result;
        }

        $models = $this->extractModelsFromResult($result);

        if ($models->isEmpty()) {
            $this->relationshipPaginations = [];
            return $result;
        }

        foreach ($models as $model) {
            foreach ($this->relationshipPaginations as $relationship => $options) {
                if (!method_exists($model, $relationship)) {
                    continue;
                }

                $relation = $model->{$relationship}();

                if (!$relation instanceof Relation) {
                    continue;
                }

                $this->applyRelationshipQueryOptions($relation, $options);

                $paginate = $options['paginate'] ?? [];
                $perPage = (int) ($paginate['per_page'] ?? ($this->config['default_per_page'] ?? 25));
                $perPage = $perPage > 0 ? min($perPage, $this->config['max_per_page'] ?? 250) : ($this->config['default_per_page'] ?? 25);
                $page = max((int) ($paginate['page'] ?? 1), 1);

                $paginator = $relation->paginate($perPage, ['*'], 'page', $page);

                $model->setRelation($relationship, $paginator);
            }
        }

        $this->relationshipPaginations = [];

        return $result;
    }

    /**
     * Prepare a collection of models from different possible result types.
     */
    private function extractModelsFromResult(mixed $result): Collection
    {
        if ($result instanceof Model) {
            return collect([$result]);
        }

        if ($result instanceof LengthAwarePaginator) {
            return $result->getCollection();
        }

        if ($result instanceof Paginator) {
            return collect($result->items());
        }

        if ($result instanceof EloquentCollection) {
            return $result;
        }

        if ($result instanceof Collection) {
            return $result;
        }

        return collect();
    }

    /**
     * Build the Eloquent query dynamically based on request parameters.
     */
    protected function buildQuery(): ?Builder
    {
        if (!$this->request || !$this->model) {
            return null;
        }

        $select = $this->decodeJsonQuery('select');
        $filters = $this->decodeJsonQuery('filters', []);
        $orFilters = $this->decodeJsonQuery('orFilters', []);
        $filtersIn = $this->decodeJsonQuery('filtersIn');
        $order = $this->decodeJsonQuery('order');
        $groupBy = $this->decodeJsonQuery('groupBy');
        $relationship = $this->decodeJsonQuery('relationship', []);
        $filtersByRelationship = $this->decodeJsonQuery('relationshipFilter', []);

        $query = is_string($this->model) ? $this->model::query() : $this->model::query();

        if ($filtersByRelationship) {
            $query = $this->buildFiltersRelationship($query, $filtersByRelationship);
        }
        if ($filtersIn) {
            $query = $this->buildWhereInQuery($query, $filtersIn);
        }
        $query = $this->buildCombinedFiltersQuery($query, $filters, $orFilters);

        if ($groupBy) {
            $query = $this->buildGroupByQuery($query, $groupBy);
        }
        if ($order) {
            $query = $this->buildOrderQuery($query, $order);
        }
        if ($select) {
            $query = $this->buildSelectQuery($query, $select);
        }
        if ($relationship) {
            $query = $this->buildRelationship($query, $relationship);
        }

        return $query;
    }

    /**
     * Check if any query parameters are present
     */
    private function hasQueryParameters(): bool
    {
        return $this->request->query('select') ||
            $this->request->query('filters') ||
            $this->request->query('filtersIn') ||
            $this->request->query('orFilters') ||
            $this->request->query('groupBy') ||
            $this->request->query('order') ||
            $this->request->query('relationship') ||
            $this->request->query('relationshipFilter');
    }

    /**
     * Get collections based on request parameters.
     */
    public function getCollections()
    {
        $cacheKey = $this->getCacheKey('collections');

        if ($this->shouldUseCache() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $query = $this->buildQuery();

        $result = $this->hasQueryParameters() && $query
            ? $query->get()
            : (is_string($this->model) ? $this->model::all() : $this->model::all());

        $result = $this->applyRelationshipPaginations($result);

        if ($this->shouldUseCache()) {
            Cache::put($cacheKey, $result, $this->config['cache_ttl'] ?? 3600);
        }

        return $result;
    }

    /**
     * Get paginated collections based on request parameters.
     */
    public function getCollectionsPaginated()
    {
        $perPage = min(
            (int) $this->request->input('per_page', $this->config['default_per_page'] ?? 25),
            $this->config['max_per_page'] ?? 250
        );

        $query = $this->buildQuery();

        $result = $this->hasQueryParameters() && $query
            ? $query->paginate($perPage)
            : (is_string($this->model) ? $this->model::paginate($perPage) : $this->model::paginate($perPage));

        return $this->applyRelationshipPaginations($result);
    }

    /**
     * Get a single item based on request parameters.
     */
    public function getOne()
    {
        $query = $this->buildQuery();

        if (!$query) {
            return null;
        }

        if (
            $this->request->query('filters') === null &&
            $this->request->query('orFilters') === null &&
            $this->request->query('order') === null
        ) {
            $query->orderByDesc('id');
        }

        $result = $query->first();

        return $this->applyRelationshipPaginations($result);
    }

    /**
     * Apply combined filters (AND/OR) on the query.
     */
    private function buildCombinedFiltersQuery(Builder|Relation $query, array $filters = [], array $orFilters = [])
    {
        if (!empty($filters) || !empty($orFilters)) {
            $query->where(function ($q) use ($filters, $orFilters) {
                // Apply filters (AND)
                foreach ($filters as $filter) {
                    if (is_array($filter) && count($filter) === 3) {
                        $field = $filter[0];
                        $operator = $filter[1];
                        $value = $filter[2];

                        // Special support for 'in' operator
                        if (strtolower($operator) === 'in') {
                            $q->whereIn($field, is_array($value) ? $value : [$value]);
                        } else {
                            $q->where($field, $operator, $value);
                        }
                    } elseif (is_array($filter)) {
                        $q->where($filter);
                    }
                }
                // Apply orFilters (OR)
                foreach ($orFilters as $orFilter) {
                    if (is_array($orFilter) && count($orFilter) === 3) {
                        $field = $orFilter[0];
                        $operator = $orFilter[1];
                        $value = $orFilter[2];

                        // Special support for 'in' operator with OR
                        if (strtolower($operator) === 'in') {
                            $q->orWhereIn($field, is_array($value) ? $value : [$value]);
                        } else {
                            $q->orWhere($field, $operator, $value);
                        }
                    } elseif (is_array($orFilter)) {
                        $q->orWhere($orFilter);
                    }
                }
            });
        }
        return $query;
    }

    /**
     * Generate cache key for query results
     */
    private function getCacheKey(string $type): string
    {
        $modelClass = is_string($this->model) ? $this->model : get_class($this->model);
        $params = $this->request->query();
        ksort($params);

        return 'auto_crud:' . $type . ':' . md5($modelClass . serialize($params));
    }

    /**
     * Check if caching should be used
     */
    private function shouldUseCache(): bool
    {
        return $this->config['enable_caching'] ?? false;
    }

    /**
     * Clear cache for this model
     */
    public function clearCache(): void
    {
        $modelClass = is_string($this->model) ? $this->model : get_class($this->model);
        $pattern = 'auto_crud:*:' . md5($modelClass . '*');

        // Note: This is a simplified cache clearing. In production, you might want to use tags
        Cache::forget($this->getCacheKey('collections'));
    }
}
