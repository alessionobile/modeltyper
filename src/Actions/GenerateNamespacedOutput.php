<?php

namespace FumeApp\ModelTyper\Actions;

use FumeApp\ModelTyper\Traits\ClassBaseName;
use FumeApp\ModelTyper\Traits\ModelRefClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class GenerateNamespacedOutput
{
    use ClassBaseName;
    use ModelRefClass;

    protected string $output = '';
    protected string $indent = '';

    /**
     * @var array<int, ReflectionClass>
     */
    protected array $enumReflectors = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $imports = [];

    /**
     * Generate TypeScript definitions in a single file with namespaces.
     *
     * @param  Collection<int, SplFileInfo>  $models
     * @param  array<string, string>  $mappings
     *
     * @throws \ReflectionException
     */
    public function __invoke(
        Collection $models,
        array $mappings,
        bool $global = false,
        bool $useEnums = false,
        bool $useTypes = false,
        bool $plurals = false,
        bool $apiResources = false,
        bool $optionalRelations = false,
        bool $noRelations = false,
        bool $noHidden = false,
        bool $noCounts = false,
        bool $optionalCounts = false,
        bool $noExists = false,
        bool $optionalExists = false,
        bool $noSums = false,
        bool $optionalSums = false,
        bool $optionalNullables = false,
        bool $fillables = false,
        string $fillableSuffix = 'Fillable',
        bool $preserveNamespaceStructure = false
    ): string {
        $modelBuilder = app(BuildModelDetails::class);
        $colAttrWriter = app(WriteColumnAttribute::class);
        $relationWriter = app(WriteRelationship::class);
        $countWriter = app(WriteCount::class);
        $existWriter = app(WriteExist::class);
        $sumWriter = app(WriteSum::class);

        // Group models by their directory
        $groupedModels = $this->groupModelsByDirectory($models, $preserveNamespaceStructure);

        if ($global) {
            $namespace = Config::get('modeltyper.global-namespace', 'models');
            $this->output .= 'export {}' . PHP_EOL . 'declare global {' . PHP_EOL . "  export namespace {$namespace} {" . PHP_EOL . PHP_EOL;
            $this->indent = '    ';
        }

        foreach ($groupedModels as $groupName => $modelGroup) {
            // Skip creating namespace for root models
            $createNamespace = $groupName !== 'models';
            $namespaceIndent = $createNamespace ? '  ' : '';
            
            if ($createNamespace) {
                $namespaceName = $this->formatNamespaceName($groupName);
                $this->output .= "{$this->indent}export namespace {$namespaceName} {" . PHP_EOL;
            }

            foreach ($modelGroup as $model) {
                $entry = '';
                $modelDetails = $modelBuilder(
                    modelFile: $model,
                    includedModels: Config::get('modeltyper.included_models', []),
                    excludedModels: Config::get('modeltyper.excluded_models', []),
                );

                if ($modelDetails === null) {
                    continue;
                }

                [
                    'reflectionModel' => $reflectionModel,
                    'name' => $name,
                    'columns' => $columns,
                    'nonColumns' => $nonColumns,
                    'relations' => $relations,
                    'interfaces' => $interfaces,
                    'imports' => $modelImports,
                    'sums' => $sums,
                ] = $modelDetails;

                $this->imports = array_merge($this->imports, $modelImports->toArray());

                $declarationType = $useTypes ? 'type' : 'interface';
                $openBrace = $useTypes ? ' = {' : ' {';
                $entry .= "{$this->indent}{$namespaceIndent}export {$declarationType} {$name}{$openBrace}" . PHP_EOL;

                if ($columns->isNotEmpty()) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // columns" . PHP_EOL;
                    $columns->each(function ($att) use (&$entry, $reflectionModel, $colAttrWriter, $noHidden, $optionalNullables, $mappings, $useEnums, $namespaceIndent) {
                        [$line, $enum] = $colAttrWriter(reflectionModel: $reflectionModel, attribute: $att, mappings: $mappings, indent: $this->indent . $namespaceIndent, noHidden: $noHidden, optionalNullables: $optionalNullables, useEnums: $useEnums);
                        if (! empty($line)) {
                            $entry .= $line;
                            if ($enum) {
                                $this->enumReflectors[] = $enum;
                            }
                        }
                    });
                }

                if ($nonColumns->isNotEmpty()) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // mutators" . PHP_EOL;
                    $nonColumns->each(function ($att) use (&$entry, $reflectionModel, $colAttrWriter, $noHidden, $optionalNullables, $mappings, $useEnums, $namespaceIndent) {
                        [$line, $enum] = $colAttrWriter(reflectionModel: $reflectionModel, attribute: $att, mappings: $mappings, indent: $this->indent . $namespaceIndent, noHidden: $noHidden, optionalNullables: $optionalNullables, useEnums: $useEnums);
                        if (! empty($line)) {
                            $entry .= $line;
                            if ($enum) {
                                $this->enumReflectors[] = $enum;
                            }
                        }
                    });
                }

                if ($interfaces->isNotEmpty()) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // overrides" . PHP_EOL;
                    $interfaces->each(function ($interface) use (&$entry, $reflectionModel, $colAttrWriter, $mappings, $namespaceIndent) {
                        [$line] = $colAttrWriter(reflectionModel: $reflectionModel, attribute: $interface, mappings: $mappings, indent: $this->indent . $namespaceIndent);
                        $entry .= $line;
                    });
                }

                if ($relations->isNotEmpty() && ! $noRelations) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // relations" . PHP_EOL;
                    $relations->each(function ($rel) use (&$entry, $relationWriter, $optionalRelations, $plurals, $namespaceIndent, $groupedModels, $groupName) {
                        // Check if the related model is in a different namespace
                        $relatedName = $rel['related'];
                        $relatedNamespace = $this->findModelNamespace($relatedName, $groupedModels);
                        
                        if ($relatedNamespace && $relatedNamespace !== $groupName) {
                            // Prefix with namespace if in different namespace
                            $rel['related'] = $this->formatNamespaceName($relatedNamespace) . '.' . $rel['related'];
                        }
                        
                        $entry .= $relationWriter(relation: $rel, indent: $this->indent . $namespaceIndent, optionalRelation: $optionalRelations, plurals: $plurals);
                    });
                }

                if ($relations->isNotEmpty() && ! $noCounts) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // counts" . PHP_EOL;
                    $relations->each(function ($rel) use (&$entry, $countWriter, $optionalCounts, $namespaceIndent) {
                        $entry .= $countWriter(relation: $rel, indent: $this->indent . $namespaceIndent, optionalCounts: $optionalCounts);
                    });
                }

                if ($relations->isNotEmpty() && ! $noExists) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // exists" . PHP_EOL;
                    $relations->each(function ($rel) use (&$entry, $existWriter, $optionalExists, $namespaceIndent) {
                        $entry .= $existWriter(relation: $rel, indent: $this->indent . $namespaceIndent, optionalExists: $optionalExists);
                    });
                }

                if ($sums->isNotEmpty() && ! $noSums) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // sums" . PHP_EOL;
                    $sums->each(function ($sum) use (&$entry, $sumWriter, $optionalSums, $namespaceIndent) {
                        $entry .= $sumWriter(sum: $sum, indent: $this->indent . $namespaceIndent, optionalSums: $optionalSums);
                    });
                }

                $entry .= "{$this->indent}{$namespaceIndent}}" . PHP_EOL;

                if ($plurals) {
                    $plural = Str::plural($name);
                    $entry .= "{$this->indent}{$namespaceIndent}export type $plural = {$name}[]" . PHP_EOL;

                    if ($apiResources) {
                        $apiDeclarationType = $useTypes ? 'type' : 'interface';
                        $apiOpenBrace = $useTypes ? ' = api.MetApiResults & { data: ' . $plural . ' }' : ' extends api.MetApiResults { data: ' . $plural . ' }';
                        $entry .= "{$this->indent}{$namespaceIndent}export {$apiDeclarationType} {$name}Results{$apiOpenBrace}" . PHP_EOL;
                    }
                }

                if ($apiResources) {
                    $apiDeclarationType = $useTypes ? 'type' : 'interface';
                    $apiResultOpenBrace = $useTypes ? ' = api.MetApiResults & { data: ' . $name . ' }' : ' extends api.MetApiResults { data: ' . $name . ' }';
                    $apiDataOpenBrace = $useTypes ? ' = api.MetApiData & { data: ' . $name . ' }' : ' extends api.MetApiData { data: ' . $name . ' }';
                    $apiResponseOpenBrace = $useTypes ? ' = api.MetApiResponse & { data: ' . $name . 'MetApiData }' : ' extends api.MetApiResponse { data: ' . $name . 'MetApiData }';

                    $entry .= "{$this->indent}{$namespaceIndent}export {$apiDeclarationType} {$name}Result{$apiResultOpenBrace}" . PHP_EOL;
                    $entry .= "{$this->indent}{$namespaceIndent}export {$apiDeclarationType} {$name}MetApiData{$apiDataOpenBrace}" . PHP_EOL;
                    $entry .= "{$this->indent}{$namespaceIndent}export {$apiDeclarationType} {$name}Response{$apiResponseOpenBrace}" . PHP_EOL;
                }

                if ($fillables) {
                    $fillableAttributes = $reflectionModel->newInstanceWithoutConstructor()->getFillable();
                    $fillablesUnion = implode(' | ', array_map(fn ($fillableAttribute) => "'$fillableAttribute'", $fillableAttributes));
                    $entry .= "{$this->indent}{$namespaceIndent}export type {$name}{$fillableSuffix} = Pick<$name, $fillablesUnion>" . PHP_EOL;
                }

                $entry .= PHP_EOL;
                $this->output .= $entry;
            }

            // Add enums for this namespace
            collect($this->enumReflectors)
                ->unique(fn (ReflectionClass $reflector) => $reflector->getName())
                ->each(function (ReflectionClass $reflector) use ($useEnums, $namespaceIndent) {
                    $this->output .= app(WriteEnumConst::class)($reflector, $this->indent . $namespaceIndent, false, $useEnums);
                });

            if ($createNamespace) {
                $this->output .= "{$this->indent}}" . PHP_EOL . PHP_EOL;
            }
        }

        // Add imports
        collect($this->imports)
            ->unique()
            ->each(function ($import) {
                $importTypeWithoutGeneric = Str::before($import['type'], '<');
                $entry = "import { {$importTypeWithoutGeneric} } from '{$import['import']}'" . PHP_EOL;
                $this->output = $entry . $this->output;
            });

        if ($global) {
            $this->output .= '  }' . PHP_EOL . '}' . PHP_EOL . PHP_EOL;
        }

        return substr($this->output, 0, strrpos($this->output, PHP_EOL));
    }

    /**
     * Group models by their directory structure.
     *
     * @param  Collection<int, SplFileInfo>  $models
     * @param  bool  $preserveNamespaceStructure
     * @return array<string, Collection<int, SplFileInfo>>
     */
    protected function groupModelsByDirectory(Collection $models, bool $preserveNamespaceStructure): array
    {
        $grouped = [];

        foreach ($models as $model) {
            $class = app()->getNamespace() . str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($model->getPathname(), app_path() . DIRECTORY_SEPARATOR)
            );

            // Extract the namespace path
            $namespaceParts = explode('\\', $class);
            array_pop($namespaceParts); // Remove the class name

            // Remove the app namespace
            $appNamespaceParts = explode('\\', trim(app()->getNamespace(), '\\'));
            $namespaceParts = array_slice($namespaceParts, count($appNamespaceParts));

            if (empty($namespaceParts) || (count($namespaceParts) === 1 && $namespaceParts[0] === 'Models')) {
                // Root models or directly under Models
                $groupKey = 'models';
            } else {
                // Remove 'Models' from the beginning if present
                if ($namespaceParts[0] === 'Models') {
                    array_shift($namespaceParts);
                }

                if ($preserveNamespaceStructure) {
                    // Use full path structure
                    $groupKey = strtolower(implode('/', $namespaceParts));
                } else {
                    // Use only immediate parent directory
                    $groupKey = strtolower($namespaceParts[0] ?? 'models');
                }
            }

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = collect();
            }

            $grouped[$groupKey]->push($model);
        }

        return $grouped;
    }

    /**
     * Format namespace name for TypeScript.
     *
     * @param  string  $groupName
     * @return string
     */
    protected function formatNamespaceName(string $groupName): string
    {
        // Convert to PascalCase for TypeScript namespace
        $parts = explode('/', $groupName);
        return collect($parts)
            ->map(fn($part) => Str::studly($part))
            ->implode('');
    }

    /**
     * Find which namespace a model belongs to.
     *
     * @param  string  $modelName
     * @param  array<string, Collection<int, SplFileInfo>>  $groupedModels
     * @return string|null
     */
    protected function findModelNamespace(string $modelName, array $groupedModels): ?string
    {
        foreach ($groupedModels as $groupName => $models) {
            foreach ($models as $model) {
                $class = app()->getNamespace() . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($model->getPathname(), app_path() . DIRECTORY_SEPARATOR)
                );
                
                if (Str::endsWith($class, '\\' . $modelName)) {
                    return $groupName;
                }
            }
        }
        
        return null;
    }
}