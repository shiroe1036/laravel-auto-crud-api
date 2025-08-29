<?php

namespace FivoTech\LaravelAutoCrud\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

interface AutoCrudControllerInterface
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse;

    /**
     * Display the specified resource.
     */
    public function show(int|string $id, ?Request $request): JsonResponse;

    /**
     * Update the specified resource in storage.
     */
    public function update(int|string $id, ?Request $request): JsonResponse;

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id, ?Request $request): JsonResponse;

    /**
     * Get paginated collection
     */
    public function paginateCollection(?Request $request): JsonResponse;

    /**
     * Get a single item using query builder
     */
    public function getOne(?Request $request): JsonResponse;
}
