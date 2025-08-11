<?php

namespace Tests\Feature\Actions;

use FumeApp\ModelTyper\Actions\GenerateMultiFileOutput;
use FumeApp\ModelTyper\Actions\GetModels;
use Tests\TestCase;

class GenerateMultiFileOutputTest extends TestCase
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
    public function test_generates_separate_files_for_different_directories(): void
    {
        // Get all models
        $models = app(GetModels::class)();
        
        // Generate multi-file output
        $results = app(GenerateMultiFileOutput::class)(
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
        
        // Assert we have multiple files
        $this->assertIsArray($results);
        $this->assertArrayHasKey('box.d.ts', $results);
        $this->assertArrayHasKey('product.d.ts', $results);
        
        // Check Box namespace contains Box and Image models
        $boxContent = $results['box.d.ts'];
        $this->assertStringContainsString('export interface Box {', $boxContent);
        $this->assertStringContainsString('export interface Image {', $boxContent);
        $this->assertStringContainsString('box_id:', $boxContent);
        
        // Check Product namespace contains Product and Image models
        $productContent = $results['product.d.ts'];
        $this->assertStringContainsString('export interface Product {', $productContent);
        $this->assertStringContainsString('export interface Image {', $productContent);
        $this->assertStringContainsString('product_id:', $productContent);
        
        // Ensure the Image models are different (different properties)
        $this->assertStringNotContainsString('box_id:', $productContent);
        $this->assertStringNotContainsString('product_id:', $boxContent);
    }
    
    public function test_preserves_full_namespace_structure(): void
    {
        // Get all models
        $models = app(GetModels::class)();
        
        // Generate multi-file output with preserve namespace structure
        $results = app(GenerateMultiFileOutput::class)(
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
            preserveNamespaceStructure: true
        );
        
        // With preserveNamespaceStructure, files should be named differently
        $this->assertIsArray($results);
        
        // Files should still be grouped by directory
        $this->assertArrayHasKey('box.d.ts', $results);
        $this->assertArrayHasKey('product.d.ts', $results);
    }
}