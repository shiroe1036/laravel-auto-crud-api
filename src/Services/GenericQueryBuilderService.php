<?php

namespace FivoTech\LaravelAutoCrud\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class GenericQueryBuilderService
{
    protected ?Request $request;
    protected Model|string|null $model;
    protected array $config;

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
    private function buildWhereInQuery(Builder $query, array $whereInData): Builder
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
    private function buildOrderQuery(Builder $query, array $order): Builder
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
    private function buildSelectQuery(Builder $query, array $select): Builder
    {
        if (!empty($select)) {
            $query->select($select);
        }
        return $query;
    }

    /**
     * Apply groupBy on the query.
     */
    private function buildGroupByQuery(Builder $query, array $groupBy): Builder
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
        foreach ($relationships as $rel) {
            if (isset($rel['query'])) {
                $with[$rel['key']] = function ($q) use ($rel) {
                    $filters = $rel['query']['filters'] ?? [];
                    $orFilters = $rel['query']['orFilters'] ?? [];
                    $filtersIn = $rel['query']['filtersIn'] ?? null;
                    $order = $rel['query']['order'] ?? null;
                    $groupBy = $rel['query']['groupBy'] ?? null;

                    $this->buildCombinedFiltersQuery($q, $filters, $orFilters);
                    if ($filtersIn) $this->buildWhereInQuery($q, $filtersIn);
                    if ($order) $this->buildOrderQuery($q, $order);
                    if ($groupBy) $this->buildGroupByQuery($q, $groupBy);
                };
            } else {
                $with[] = $rel['key'];
            }
        }
        if (!empty($with)) {
            $query->with($with);
        }
        return $query;
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

        return $this->hasQueryParameters() && $query
            ? $query->paginate($perPage)
            : (is_string($this->model) ? $this->model::paginate($perPage) : $this->model::paginate($perPage));
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

        return $query->first();
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
