<?php

namespace FivoTech\LaravelAutoCrud\Examples;

use FivoTech\LaravelAutoCrud\Controllers\AutoCrudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Example: Multitenant Controller using Hooks
 *
 * This example shows how to implement multitenant functionality
 * using the new flexible hook system.
 */
class MultitenantController extends AutoCrudController
{
    public function __construct($model = null, array $options = [])
    {
        // Define multitenant hooks
        $multitenantHooks = [
            'authorization' => [$this, 'handleMultitenantAuth'],
            'preprocess_data' => [$this, 'addEnseigneIdToData'],
            'preprocess_bulk_data' => [$this, 'addEnseigneIdToBulkData'],
        ];

        // Merge with any provided hooks
        $options['hooks'] = array_merge($multitenantHooks, $options['hooks'] ?? []);

        parent::__construct($model, $options);
    }

    /**
     * Handle multitenant authorization
     */
    public function handleMultitenantAuth(Request $request, string $methodName, $model): bool
    {
        // Methods that don't require enseigne verification
        $excludeMethods = ['getOne']; // Configure as needed

        if (in_array($methodName, $excludeMethods)) {
            return true;
        }

        if (!$request->has('enseigneId')) {
            return false;
        }

        $enseigneId = (int) $request->get('enseigneId');
        return $this->assertUserEnseigne($enseigneId);
    }

    /**
     * Add enseigne_id to single data
     */
    public function addEnseigneIdToData(array $data, Request $request, string $operation, $model): array
    {
        $enseigneId = $request->get('enseigneId');

        if ($enseigneId && $this->shouldAddEnseigneId($model)) {
            $data['enseigne_id'] = $enseigneId;
        }

        return $data;
    }

    /**
     * Add enseigne_id to bulk data
     */
    public function addEnseigneIdToBulkData(array $dataArray, Request $request, $model): array
    {
        $enseigneId = $request->get('enseigneId');

        if ($enseigneId && $this->shouldAddEnseigneId($model)) {
            foreach ($dataArray as $idx => $data) {
                $dataArray[$idx]['enseigne_id'] = $enseigneId;
            }
        }

        return $dataArray;
    }

    /**
     * Check if enseigne_id should be added to the model
     */
    protected function shouldAddEnseigneId($model): bool
    {
        return property_exists($model, 'multitenantField') &&
            in_array('enseigne_id', $model::$multitenantField);
    }

    /**
     * Assert user has access to the enseigne
     */
    protected function assertUserEnseigne($enseigneId): bool
    {
        $userConnected = Auth::user();
        if (!$userConnected) {
            return false;
        }

        $keyUserEnseigneCache = "user_enseigne_{$userConnected->id}";
        $keyUserEnseigneActivateCache = "user_enseigne_{$userConnected->id}_activate";

        Cache::put($keyUserEnseigneActivateCache, $enseigneId);

        $userEnseigneCache = Cache::get($keyUserEnseigneCache);

        if (!$userEnseigneCache) {
            // Replace with your actual UserEnseigne model
            $userEnseigneModel = 'App\Models\Multitenant\UsersEnseignes';
            $userEnseigneCache = $userEnseigneModel::where('user_id', $userConnected->id)->get();
            Cache::put($keyUserEnseigneCache, $userEnseigneCache);
        }

        $enseigneUserIds = array_map(fn($item) => $item['enseigne_id'], $userEnseigneCache->toArray());

        return $enseigneId && $enseigneUserIds ? in_array($enseigneId, $enseigneUserIds) : false;
    }
}

/**
 * Example: Custom Authorization Controller
 *
 * Shows how to implement custom authorization logic
 */
class CustomAuthController extends AutoCrudController
{
    public function __construct($model = null, array $options = [])
    {
        $customHooks = [
            'authorization' => [$this, 'customAuthorization'],
            'preprocess_data' => [$this, 'validateAndTransformData'],
            'postprocess_response' => [$this, 'addMetadata'],
        ];

        $options['hooks'] = array_merge($customHooks, $options['hooks'] ?? []);
        parent::__construct($model, $options);
    }

    public function customAuthorization(Request $request, string $methodName, $model): bool
    {
        // Example: Only allow admins to delete (customize based on your user model)
        if ($methodName === 'destroy') {
            $user = Auth::user();
            // Replace with your actual role checking logic
            return $user && isset($user->role) && $user->role === 'admin';
        }

        // Example: Only allow owners to update
        if ($methodName === 'update') {
            $itemId = $request->route('id');
            $item = $model::find($itemId);
            return $item && $item->user_id === Auth::id();
        }

        return true;
    }

    public function validateAndTransformData(array $data, Request $request, string $operation, $model): array
    {
        // Add user_id for create operations
        if ($operation === 'store' && Auth::check()) {
            $data['user_id'] = Auth::id();
        }

        // Transform data as needed
        if (isset($data['name'])) {
            $data['name'] = ucwords(strtolower($data['name']));
        }

        return $data;
    }

    public function addMetadata($response, Request $request, string $operation, $model)
    {
        // Add metadata to responses
        if (is_array($response) && !isset($response['message'])) {
            return [
                'data' => $response,
                'meta' => [
                    'operation' => $operation,
                    'timestamp' => now()->toISOString(),
                    'user_id' => Auth::id(),
                ]
            ];
        }

        return $response;
    }
}

/**
 * Example: Audit Trail Controller
 *
 * Shows how to implement audit functionality
 */
class AuditTrailController extends AutoCrudController
{
    public function __construct($model = null, array $options = [])
    {
        $auditHooks = [
            'preprocess_data' => [$this, 'addAuditFields'],
            'before_delete' => [$this, 'logDeletion'],
            'postprocess_response' => [$this, 'logActivity'],
        ];

        $options['hooks'] = array_merge($auditHooks, $options['hooks'] ?? []);
        parent::__construct($model, $options);
    }

    public function addAuditFields(array $data, Request $request, string $operation, $model): array
    {
        $user = Auth::user();

        if ($operation === 'store') {
            $data['created_by'] = $user?->id;
            $data['created_at'] = now();
        } elseif ($operation === 'update') {
            $data['updated_by'] = $user?->id;
            $data['updated_at'] = now();
        }

        return $data;
    }

    public function logDeletion($model, Request $request): void
    {
        // Log deletion activity
        \Illuminate\Support\Facades\Log::info('Model deleted', [
            'model' => get_class($model),
            'id' => $model->id,
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
        ]);
    }

    public function logActivity($response, Request $request, string $operation, $model)
    {
        // Log all activities
        \Illuminate\Support\Facades\Log::info('CRUD operation performed', [
            'operation' => $operation,
            'model' => is_string($model) ? $model : get_class($model),
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
