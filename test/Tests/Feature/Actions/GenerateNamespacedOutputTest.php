<?php

namespace Tests\Feature\Actions;

use FumeApp\ModelTyper\Actions\GenerateNamespacedOutput;
use FumeApp\ModelTyper\Actions\GetModels;
use Tests\TestCase;

class GenerateNamespacedOutputTest extends TestCase
{
    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        
        // Load our custom migrations for Box and Product models
        $this->loadMigrationsFrom(
            __DIR__ . '/../../../laravel-skeleton/database/migrations'
        );
    }
    
    public function test_generates_namespaced_output(): void
    {
        // Get all models
        $models = app(GetModels::class)();
        
        // Generate namespaced output
        $result = app(GenerateNamespacedOutput::class)(
            models: $models,
            mappings: [
                'string' => 'string',
                'boolean' => 'boolean',
                'integer' => 'number',
                'decimal' => 'number',
            ],
            global: false,
            useEnums: false,
            useTypes: false,
            plurals: false,
            apiResources: false,
            optionalRelations: false,
            noRelations: false,
            noHidden: false,
            noCounts: false,
            optionalCounts: false,
            noExists: false,
            optionalExists: false,
            noSums: false,
            optionalSums: false,
            optionalNullables: false,
            fillables: false,
            fillableSuffix: 'Fillable',
            preserveNamespaceStructure: false
        );
        
        // Assert we have a single string output
        $this->assertIsString($result);
        
        // Debug: Output the result to see the actual structure (comment out after testing)
        // echo "\n\n=== GENERATED OUTPUT ===\n" . $result . "\n=== END OUTPUT ===\n\n";
        
        // Check for Box namespace
        $this->assertStringContainsString('export namespace Box {', $result);
        
        // Check for Product namespace
        $this->assertStringContainsString('export namespace Product {', $result);
        
        // Check that both namespaces have Image interface
        // Count occurrences of 'export interface Image'
        $imageCount = substr_count($result, 'export interface Image');
        $this->assertGreaterThanOrEqual(2, $imageCount, 'Should have at least 2 Image interfaces in different namespaces');
        
        // Check that Box namespace contains both Box and Image interfaces
        $boxNamespaceMatch = preg_match('/export namespace Box \{.*?export interface Box \{.*?\}.*?export interface Image \{.*?\}/s', $result);
        $this->assertEquals(1, $boxNamespaceMatch, 'Box namespace should contain both Box and Image interfaces');
        
        // Check that Product namespace contains both Product and Image interfaces  
        $productNamespaceMatch = preg_match('/export namespace Product \{.*?export interface Image \{.*?\}.*?export interface Product \{.*?\}/s', $result);
        $this->assertEquals(1, $productNamespaceMatch, 'Product namespace should contain both Product and Image interfaces');
    }
    
    public function test_handles_cross_namespace_relations(): void
    {
        // Get all models
        $models = app(GetModels::class)();
        
        // Generate namespaced output with relations
        $result = app(GenerateNamespacedOutput::class)(
            models: $models,
            mappings: [
                'string' => 'string',
                'boolean' => 'boolean',
                'integer' => 'number',
                'decimal' => 'number',
            ],
            global: false,
            useEnums: false,
            useTypes: false,
            plurals: false,
            apiResources: false,
            optionalRelations: false,
            noRelations: false,
            noHidden: false,
            noCounts: false,
            optionalCounts: false,
            noExists: false,
            optionalExists: false,
            noSums: false,
            optionalSums: false,
            optionalNullables: false,
            fillables: false,
            fillableSuffix: 'Fillable',
            preserveNamespaceStructure: false
        );
        
        // Relations within the same namespace should not be prefixed
        $this->assertStringContainsString('images: Image[]', $result);
        $this->assertStringContainsString('box: Box', $result);
        $this->assertStringContainsString('product: Product', $result);
    }
}