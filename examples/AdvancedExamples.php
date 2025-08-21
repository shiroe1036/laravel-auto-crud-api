<?php

namespace FivoTech\LaravelAutoCrud\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;

/**
 * Advanced Examples showing various customization patterns
 * for the Laravel Auto CRUD package.
 */

// Example 1: Complete Data Processing Pipeline
class DataProcessingController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $hooks = [
            'authorization' => [$this, 'checkPermissions'],
            'preprocess_data' => [$this, 'processInputData'],
            'postprocess_response' => [$this, 'enrichResponse'],
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }

    public function checkPermissions($request, $method, $model)
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Example role-based authorization
        switch ($method) {
            case 'index':
            case 'show':
                return true; // Everyone can read
            case 'store':
            case 'update':
                return $user->hasRole('editor') || $user->hasRole('admin');
            case 'destroy':
                return $user->hasRole('admin');
            default:
                return false;
        }
    }

    public function processInputData($data, $request, $operation, $model)
    {
        // Clean and validate data
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        if (isset($data['name'])) {
            $data['name'] = ucwords(strtolower(trim($data['name'])));
        }

        // Add audit fields
        if ($operation === 'store') {
            $data['created_by'] = Auth::id();
            $data['created_at'] = now();
        } elseif ($operation === 'update') {
            $data['updated_by'] = Auth::id();
            $data['updated_at'] = now();
        }

        return $data;
    }

    public function enrichResponse($response, $request, $operation, $model)
    {
        // Add metadata to responses
        if (is_array($response) || is_object($response)) {
            $enriched = [
                'data' => $response,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'operation' => $operation,
                    'user_id' => Auth::id(),
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ];

            // Add pagination info if available
            if (isset($response['data']) && isset($response['links'])) {
                $enriched['pagination'] = [
                    'current_page' => $response['current_page'] ?? null,
                    'total' => $response['total'] ?? null,
                    'per_page' => $response['per_page'] ?? null,
                ];
            }

            return $enriched;
        }

        return $response;
    }
}

// Example 2: Caching Controller
class CachingController extends AutoCrudController
{
    protected string $cachePrefix = 'autocrud';
    protected int $cacheTtl = 3600; // 1 hour

    public function __construct($model = null)
    {
        $hooks = [
            'postprocess_response' => [$this, 'cacheResponse'],
        ];

        parent::__construct($model, [
            'hooks' => $hooks,
            'options' => ['enable_caching' => true]
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $cacheKey = $this->generateCacheKey('index', $request->query());

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($request) {
            return parent::index($request);
        });
    }

    public function cacheResponse($response, $request, $operation, $model)
    {
        // Clear cache on write operations
        if (in_array($operation, ['store', 'update', 'destroy'])) {
            $this->clearModelCache($model);
        }

        return $response;
    }

    protected function generateCacheKey(string $method, array $params): string
    {
        $modelClass = is_string($this->model) ? $this->model : get_class($this->model);
        $modelName = class_basename($modelClass);

        return sprintf(
            '%s:%s:%s:%s',
            $this->cachePrefix,
            strtolower($modelName),
            $method,
            md5(serialize($params))
        );
    }

    protected function clearModelCache($model): void
    {
        $modelName = is_string($model) ? class_basename($model) : class_basename(get_class($model));
        $pattern = sprintf('%s:%s:*', $this->cachePrefix, strtolower($modelName));

        // This would need a cache implementation that supports pattern deletion
        // For Redis: Cache::forget($pattern);
        // For now, we'll flush all cache (not ideal for production)
        Cache::flush();
    }
}

// Example 3: Validation Controller
class ValidatedController extends AutoCrudController
{
    protected array $validationRules = [];

    public function __construct($model = null, array $validationRules = [])
    {
        $this->validationRules = $validationRules;

        $hooks = [
            'preprocess_data' => [$this, 'validateData'],
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }

    public function validateData($data, $request, $operation, $model)
    {
        $rules = $this->getValidationRules($operation, $model);

        if (empty($rules)) {
            return $data;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $data;
    }

    protected function getValidationRules($operation, $model): array
    {
        $modelName = is_string($model) ? class_basename($model) : class_basename(get_class($model));

        // Check for operation-specific rules
        $key = strtolower($modelName) . '.' . $operation;
        if (isset($this->validationRules[$key])) {
            return $this->validationRules[$key];
        }

        // Check for general model rules
        $generalKey = strtolower($modelName);
        if (isset($this->validationRules[$generalKey])) {
            return $this->validationRules[$generalKey];
        }

        return [];
    }
}

// Example 4: Event-Driven Controller
class EventDrivenController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $hooks = [
            'postprocess_response' => [$this, 'dispatchEvents'],
            'before_delete' => [$this, 'beforeDelete'],
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }

    public function dispatchEvents($response, $request, $operation, $model)
    {
        $modelClass = is_string($model) ? $model : get_class($model);
        $eventClass = $this->getEventClass($modelClass, $operation);

        if ($eventClass && class_exists($eventClass)) {
            event(new $eventClass($response, $request, $operation));
        }

        // Log the operation
        Log::info("CRUD Operation: {$operation}", [
            'model' => $modelClass,
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }

    public function beforeDelete($model, $request)
    {
        // Archive before deletion
        if (method_exists($model, 'archive')) {
            $model->archive();
        }

        // Log deletion
        Log::warning("Model deletion", [
            'model' => get_class($model),
            'id' => $model->id,
            'deleted_by' => Auth::id(),
        ]);
    }

    protected function getEventClass(string $modelClass, string $operation): ?string
    {
        $modelName = class_basename($modelClass);
        $eventName = ucfirst($operation);

        // Try different event naming conventions
        $possibleClasses = [
            "App\\Events\\{$modelName}{$eventName}",
            "App\\Events\\{$modelName}\\{$eventName}",
            "App\\Events\\Crud\\{$modelName}{$eventName}",
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}

// Example 5: API Rate Limited Controller
class RateLimitedController extends AutoCrudController
{
    protected array $rateLimits = [
        'store' => 10,   // 10 creates per minute
        'update' => 20,  // 20 updates per minute
        'destroy' => 5,  // 5 deletes per minute
    ];

    public function __construct($model = null)
    {
        $hooks = [
            'authorization' => [$this, 'checkRateLimit'],
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }

    public function checkRateLimit($request, $method, $model)
    {
        if (!isset($this->rateLimits[$method])) {
            return true; // No limit for this method
        }

        $limit = $this->rateLimits[$method];
        $key = $this->getRateLimitKey($request, $method);

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            abort(429, "Rate limit exceeded for {$method} operations");
        }

        // Increment counter
        Cache::put($key, $current + 1, now()->addMinute());

        return true;
    }

    protected function getRateLimitKey($request, $method): string
    {
        $userId = Auth::id() ?? $request->ip();
        $modelName = is_string($this->model) ? class_basename($this->model) : class_basename(get_class($this->model));

        return "rate_limit:{$modelName}:{$method}:{$userId}:" . now()->format('Y-m-d-H-i');
    }
}

// Example 6: Soft Delete Controller
class SoftDeleteController extends AutoCrudController
{
    public function __construct($model = null)
    {
        $hooks = [
            'authorization' => [$this, 'checkSoftDeletePermission'],
            'before_delete' => [$this, 'performSoftDelete'],
        ];

        parent::__construct($model, ['hooks' => $hooks]);
    }

    public function checkSoftDeletePermission($request, $method, $model)
    {
        // Only allow hard deletes for super admins
        if ($method === 'destroy' && $request->has('force')) {
            $user = Auth::user();
            // Example: replace with your actual role checking logic
            // This could be: $user->role === 'super_admin' or $user->is_admin, etc.
            return $user && $user->role === 'super_admin';
        }

        return true;
    }

    public function performSoftDelete($model, $request)
    {
        if (!$request->has('force') && method_exists($model, 'delete')) {
            // Perform soft delete instead of hard delete
            $model->delete();

            return response()->json([
                'message' => 'Resource soft deleted successfully',
                'data' => $model
            ]);
        }

        // Continue with hard delete for force parameter or models without soft delete
        return null;
    }

    // Add restore functionality
    public function restore(Request $request, $id): JsonResponse
    {
        $modelClass = is_string($this->model) ? $this->model : get_class($this->model);
        $model = $modelClass::withTrashed()->findOrFail($id);

        if (method_exists($model, 'restore')) {
            $model->restore();

            return response()->json([
                'message' => 'Resource restored successfully',
                'data' => $model
            ]);
        }

        return response()->json([
            'message' => 'This model does not support restoration'
        ], 400);
    }
}
