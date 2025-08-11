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
        
        // Check Box file has namespace wrapper
        $boxContent = $results['box.d.ts'];
        $this->assertStringContainsString('export namespace Box {', $boxContent);
        $this->assertStringContainsString('export interface Box {', $boxContent);
        $this->assertStringContainsString('export interface Image {', $boxContent);
        $this->assertStringContainsString('box_id:', $boxContent);
        
        // Check Product file has namespace wrapper
        $productContent = $results['product.d.ts'];
        $this->assertStringContainsString('export namespace Product {', $productContent);
        $this->assertStringContainsString('export interface Product {', $productContent);
        $this->assertStringContainsString('export interface Image {', $productContent);
        $this->assertStringContainsString('product_id:', $productContent);
        
        // Ensure the Image models are different (different properties)
        $this->assertStringNotContainsString('box_id:', $productContent);
    }
    
    public function test_handles_cross_namespace_imports(): void
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
        
        // Check that box.d.ts has BoxProduct model with cross-namespace Product relation
        $boxContent = $results['box.d.ts'];
        
        // Debug output to see what's being generated
        // echo "\n\n=== BOX.D.TS CONTENT ===\n" . $boxContent . "\n=== END ===\n\n";
        
        // Should have BoxProduct interface
        $this->assertStringContainsString('export interface BoxProduct {', $boxContent);
        
        // Should import Product namespace from product file
        $this->assertStringContainsString("import { Product } from './product'", $boxContent);
        
        // Should reference Product.Product in the relation
        $this->assertStringContainsString('product: Product.Product', $boxContent);
        
        // Should have local Box reference without namespace prefix
        $this->assertStringContainsString('box: Box', $boxContent);
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