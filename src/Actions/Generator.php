<?php

namespace FumeApp\ModelTyper\Actions;

use FumeApp\ModelTyper\Exceptions\ModelTyperException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use ReflectionException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @throws ModelTyperException
 */
class Generator
{
    /**
     * Run the command to generate the output.
     *
     * @throws ModelTyperException
     * @throws ReflectionException
     * @return string|array<string, string>
     */
    public function __invoke(?string $specificModel = null, bool $global = false, bool $json = false, bool $useEnums = false, bool $useTypes = false, bool $plurals = false, bool $apiResources = false, bool $optionalRelations = false, bool $noRelations = false, bool $noHidden = false, bool $noCounts = false, bool $optionalCounts = false, bool $noExists = false, bool $optionalExists = false, bool $noSums = false, bool $optionalSums = false, bool $timestampsDate = false, bool $optionalNullables = false, bool $fillables = false, string $fillableSuffix = 'Fillable', string $outputMode = 'single', bool $preserveNamespaceStructure = false): string|array
    {
        $models = app(GetModels::class)(
            model: $specificModel,
            includedModels: Config::get('modeltyper.included_models', []),
            excludedModels: Config::get('modeltyper.excluded_models', [])
        );

        if ($models->isEmpty()) {
            throw new ModelTyperException('No models found.');
        }

        return $this->display(
            models: $models,
            global: $global,
            json: $json,
            useEnums: $useEnums,
            useTypes: $useTypes,
            plurals: $plurals,
            apiResources: $apiResources,
            optionalRelations: $optionalRelations,
            noRelations: $noRelations,
            noHidden: $noHidden,
            noCounts: $noCounts,
            optionalCounts: $optionalCounts,
            noExists: $noExists,
            optionalExists: $optionalExists,
            noSums: $noSums,
            optionalSums: $optionalSums,
            timestampsDate: $timestampsDate,
            optionalNullables: $optionalNullables,
            fillables: $fillables,
            fillableSuffix: $fillableSuffix,
            outputMode: $outputMode,
            preserveNamespaceStructure: $preserveNamespaceStructure
        );
    }

    /**
     * Return the command output.
     *
     * @param  Collection<int, SplFileInfo>  $models
     * @return string|array<string, string>
     *
     * @throws ReflectionException
     */
    protected function display(Collection $models, bool $global = false, bool $json = false, bool $useEnums = false, bool $useTypes = false, bool $plurals = false, bool $apiResources = false, bool $optionalRelations = false, bool $noRelations = false, bool $noHidden = false, bool $noCounts = false, bool $optionalCounts = false, bool $noExists = false, bool $optionalExists = false, bool $noSums = false, bool $optionalSums = false, bool $timestampsDate = false, bool $optionalNullables = false, bool $fillables = false, string $fillableSuffix = 'Fillable', string $outputMode = 'single', bool $preserveNamespaceStructure = false): string|array
    {
        $mappings = app(GetMappings::class)(setTimestampsToDate: $timestampsDate);

        if ($json) {
            return app(GenerateJsonOutput::class)(models: $models, mappings: $mappings, useEnums: $useEnums, noCounts: $noCounts, optionalCounts: $optionalCounts, noExists: $noExists, optionalExists: $optionalExists, noSums: $noSums, optionalSums: $optionalSums);
        }

        // Route to appropriate generator based on output mode
        if ($outputMode === 'directory') {
            return app(GenerateMultiFileOutput::class)(
                models: $models,
                mappings: $mappings,
                global: $global,
                useEnums: $useEnums,
                useTypes: $useTypes,
                plurals: $plurals,
                apiResources: $apiResources,
                optionalRelations: $optionalRelations,
                noRelations: $noRelations,
                noHidden: $noHidden,
                noCounts: $noCounts,
                optionalCounts: $optionalCounts,
                noExists: $noExists,
                optionalExists: $optionalExists,
                noSums: $noSums,
                optionalSums: $optionalSums,
                optionalNullables: $optionalNullables,
                fillables: $fillables,
                fillableSuffix: $fillableSuffix,
                preserveNamespaceStructure: $preserveNamespaceStructure
            );
        }
        
        if ($outputMode === 'namespace') {
            return app(GenerateNamespacedOutput::class)(
                models: $models,
                mappings: $mappings,
                global: $global,
                useEnums: $useEnums,
                useTypes: $useTypes,
                plurals: $plurals,
                apiResources: $apiResources,
                optionalRelations: $optionalRelations,
                noRelations: $noRelations,
                noHidden: $noHidden,
                noCounts: $noCounts,
                optionalCounts: $optionalCounts,
                noExists: $noExists,
                optionalExists: $optionalExists,
                noSums: $noSums,
                optionalSums: $optionalSums,
                optionalNullables: $optionalNullables,
                fillables: $fillables,
                fillableSuffix: $fillableSuffix,
                preserveNamespaceStructure: $preserveNamespaceStructure
            );
        }

        // Default to single file output (backward compatibility)
        return app(GenerateCliOutput::class)(
            models: $models,
            mappings: $mappings,
            global: $global,
            useEnums: $useEnums,
            useTypes: $useTypes,
            plurals: $plurals,
            apiResources: $apiResources,
            optionalRelations: $optionalRelations,
            noRelations: $noRelations,
            noHidden: $noHidden,
            noCounts: $noCounts,
            optionalCounts: $optionalCounts,
            noExists: $noExists,
            optionalExists: $optionalExists,
            noSums: $noSums,
            optionalSums: $optionalSums,
            optionalNullables: $optionalNullables,
            fillables: $fillables,
            fillableSuffix: $fillableSuffix
        );
    }
}
