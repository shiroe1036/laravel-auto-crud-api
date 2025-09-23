<?php

namespace FivoTech\LaravelAutoCrud\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;
use FivoTech\LaravelAutoCrud\Contracts\AutoCrudControllerInterface;
use App\Http\Controllers\Controller;

class AutoCrudController extends Controller implements AutoCrudControllerInterface
{
    protected $model;
    protected GenericQueryBuilderService $queryBuilderService;
    protected array $config;
    protected array $customHooks = [];

    public function __construct($model = null, array $options = [])
    {
        $this->model = $model;
        $this->queryBuilderService = app(GenericQueryBuilderService::class);
        $this->config = config('auto-crud', []);
        $this->customHooks = $options['hooks'] ?? [];
    }

    /**
     * Set the model for this controller instance
     */
    public function setModel($model): self
    {
        $this->model = $model;
        $this->queryBuilderService->setModel($model);
        return $this;
    }

    /**
     * Execute a hook if it exists
     */
    protected function executeHook(string $hookName, ...$args)
    {
        if (isset($this->customHooks[$hookName]) && is_callable($this->customHooks[$hookName])) {
            return call_user_func($this->customHooks[$hookName], ...$args);
        }
        return null;
    }

    /**
     * Check if the request should be authorized for the given method
     */
    protected function isAuthorized(Request $request, string $methodName): bool
    {
        // Execute custom authorization hook if provided
        $hookResult = $this->executeHook('authorization', $request, $methodName, $this->model);

        if ($hookResult !== null) {
            return $hookResult;
        }

        // Default: allow all requests (can be overridden in child classes)
        return true;
    }

    /**
     * Pre-process request data before operations
     */
    protected function preprocessData(Request $request, string $operation): array
    {
        $data = $request->all();

        // Execute custom preprocessing hook if provided
        $hookResult = $this->executeHook('preprocess_data', $data, $request, $operation, $this->model);

        if ($hookResult !== null) {
            return $hookResult;
        }

        return $data;
    }

    /**
     * Post-process response data after operations
     */
    protected function postprocessResponse($data, Request $request, string $operation)
    {
        // Execute custom postprocessing hook if provided
        $hookResult = $this->executeHook('postprocess_response', $data, $request, $operation, $this->model);

        return $hookResult ?? $data;
    }

    /**
     * Get list of items
     */
    public function index(?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        if (!$this->isAuthorized($request, 'index')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->queryBuilderService->setRequest($request)->setModel($this->model);
            $result = $this->queryBuilderService->getCollections();

            $result = $this->postprocessResponse($result, $request, 'index');

            return \response()->json($result, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get paginated list of items
     */
    public function paginateCollection(?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        if (!$this->isAuthorized($request, 'paginateCollection')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->queryBuilderService->setRequest($request)->setModel($this->model);
            $result = $this->queryBuilderService->getCollectionsPaginated();

            $result = $this->postprocessResponse($result, $request, 'paginateCollection');

            return \response()->json($result, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new item
     */
    public function store(?Request $request = null): JsonResponse
    {
        $request = $request ?? request();
        if (!$this->isAuthorized($request, 'store')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $dataRequest = $this->preprocessData($request, 'store');

            // Extract many-to-many relationships data
            $relationships = [];
            foreach ($dataRequest as $key => $value) {
                if (preg_match('/_ids$/', $key) && is_array($value)) {
                    $relationName = str_replace('_ids', '', $key);
                    $relationships[$relationName] = $value;
                    unset($dataRequest[$key]);
                }
            }

            // Handle bulk insert mode
            if (isset($dataRequest[0]['isUseInsertMode'])) {
                unset($dataRequest[0]);
                // Reindex after removing the flag element to avoid potential issues
                $dataRequest = array_values($dataRequest);

                // Execute bulk preprocessing hook if provided
                $preprocessedBulkData = $this->executeHook('preprocess_bulk_data', $dataRequest, $request, $this->model);
                if ($preprocessedBulkData !== null) {
                    $dataRequest = $preprocessedBulkData;
                }

                // If the model uses UUID (non-incrementing string PK), ensure each row has an ID
                if ($this->model) {
                    $modelInstance = new $this->model;
                    $primaryKey = $modelInstance->getKeyName();
                    $incrementing = $modelInstance->getIncrementing();
                    $keyType = method_exists($modelInstance, 'getKeyType') ? $modelInstance->getKeyType() : 'int';

                    if ($incrementing === false && $keyType === 'string') {
                        $dataRequest = array_map(function ($row) use ($primaryKey) {
                            if (!isset($row[$primaryKey]) || empty($row[$primaryKey])) {
                                $row[$primaryKey] = (string) Str::uuid();
                            }
                            return $row;
                        }, $dataRequest);
                    }
                }

                $result = $this->model::insert($dataRequest);

                $result = $this->postprocessResponse($result, $request, 'store_bulk');

                return \response()->json($result, 201);
            } else {
                // Execute single item preprocessing hook if provided
                $preprocessedData = $this->executeHook('preprocess_single_data', $dataRequest, $request, $this->model);
                if ($preprocessedData !== null) {
                    $dataRequest = $preprocessedData;
                }

                // Create the main model
                $model = $this->model::create($dataRequest);

                // Process many-to-many relationships
                foreach ($relationships as $relation => $ids) {
                    if (method_exists($model, $relation)) {
                        $model->$relation()->sync($ids);
                    }
                }

                $model->refresh();

                $model = $this->postprocessResponse($model, $request, 'store');

                return \response()->json($model, 201);
            }
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single item using query builder
     */
    public function getOne(?Request $request = null): JsonResponse
    {
        $request = $request ?? request();
        // Note: getOne typically doesn't need authorization for specific IDs
        // but can be customized via hooks
        if (!$this->isAuthorized($request, 'getOne')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->queryBuilderService->setRequest($request)->setModel($this->model);
            $result = $this->queryBuilderService->getOne();

            $result = $this->postprocessResponse($result, $request, 'getOne');

            return \response()->json($result, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific item
     */
    public function show(int|string $id, ?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        if (!$this->isAuthorized($request, 'show')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $data = $this->model::find($id);
            if (!$data) {
                return \response()->json(['message' => "Item with ID {$id} not found"], 404);
            }

            $data = $this->postprocessResponse($data, $request, 'show');

            return \response()->json($data, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing item
     */
    public function update(int|string $id, ?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        if (!$this->isAuthorized($request, 'update')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            if (!$this->model::where('id', $id)->exists()) {
                return \response()->json(['message' => "Item with ID {$id} not found"], 404);
            }

            $requestData = $this->preprocessData($request, 'update');

            // Extract many-to-many relationships data
            $relationships = [];
            foreach ($requestData as $key => $value) {
                if (preg_match('/_ids$/', $key) && is_array($value)) {
                    $relationName = str_replace('_ids', '', $key);
                    $relationships[$relationName] = $value;
                    unset($requestData[$key]);
                }
            }

            // Update the main model
            $this->model::where('id', $id)->update($requestData);

            // Retrieve the updated model instance
            $modelInstance = $this->model::find($id);

            // Process many-to-many relationships
            foreach ($relationships as $relation => $ids) {
                $relationCamelCase = Str::camel($relation);
                if (method_exists($modelInstance, $relationCamelCase)) {
                    $modelInstance->$relationCamelCase()->sync($ids);
                }
            }

            $response = ['message' => 'Record updated successfully'];
            $response = $this->postprocessResponse($response, $request, 'update');

            return \response()->json($response, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an item
     */
    public function destroy(int|string $id, ?Request $request = null): JsonResponse
    {
        $request = $request ?? request();

        if (!$this->isAuthorized($request, 'destroy')) {
            return \response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $model = $this->model::find($id);
            if (!$model) {
                return \response()->json(['message' => "Item with ID {$id} not found"], 404);
            }

            // Execute pre-delete hook if provided
            $this->executeHook('before_delete', $model, $request);

            $model->delete();

            $response = ['message' => 'Record deleted successfully'];
            $response = $this->postprocessResponse($response, $request, 'destroy');

            return \response()->json($response, 200);
        } catch (\Throwable $th) {
            return \response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
