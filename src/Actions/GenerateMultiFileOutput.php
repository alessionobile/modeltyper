<?php

namespace FumeApp\ModelTyper\Actions;

use FumeApp\ModelTyper\Traits\ClassBaseName;
use FumeApp\ModelTyper\Traits\ModelRefClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class GenerateMultiFileOutput
{
    use ClassBaseName;
    use ModelRefClass;

    /**
     * @var array<string, string>
     */
    protected array $outputs = [];

    /**
     * @var array<string, array<int, ReflectionClass>>
     */
    protected array $enumReflectorsByFile = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $importsByFile = [];
    
    /**
     * @var array<string, array<string, string>>
     */
    protected array $crossNamespaceImports = [];

    protected string $indent = '';

    /**
     * Generate TypeScript definitions in multiple files organized by directory.
     *
     * @param  Collection<int, SplFileInfo>  $models
     * @param  array<string, string>  $mappings
     * @return array<string, string>
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
    ): array {
        $modelBuilder = app(BuildModelDetails::class);
        $colAttrWriter = app(WriteColumnAttribute::class);
        $relationWriter = app(WriteRelationship::class);
        $countWriter = app(WriteCount::class);
        $existWriter = app(WriteExist::class);
        $sumWriter = app(WriteSum::class);

        // Group models by their directory
        $groupedModels = $this->groupModelsByDirectory($models, $preserveNamespaceStructure);
        
        // First pass: collect all model names and their namespaces for cross-reference
        $modelNamespaceMap = $this->buildModelNamespaceMap($groupedModels);

        foreach ($groupedModels as $groupName => $modelGroup) {
            $output = '';
            $enumReflectors = [];
            $imports = [];
            $crossNamespaceImports = [];
            $namespaceName = $this->formatNamespaceName($groupName);
            $namespaceIndent = '  ';

            if ($global) {
                $namespace = Config::get('modeltyper.global-namespace', 'models');
                $output .= 'export {}' . PHP_EOL . 'declare global {' . PHP_EOL . "  export namespace {$namespace} {" . PHP_EOL . PHP_EOL;
                $this->indent = '    ';
            }
            
            // Start namespace wrapper
            $output .= "export namespace {$namespaceName} {" . PHP_EOL;

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

                $imports = array_merge($imports, $modelImports->toArray());

                $declarationType = $useTypes ? 'type' : 'interface';
                $openBrace = $useTypes ? ' = {' : ' {';
                $entry .= "{$this->indent}{$namespaceIndent}export {$declarationType} {$name}{$openBrace}" . PHP_EOL;

                if ($columns->isNotEmpty()) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // columns" . PHP_EOL;
                    $columns->each(function ($att) use (&$entry, $reflectionModel, $colAttrWriter, $noHidden, $optionalNullables, $mappings, $useEnums, &$enumReflectors, $namespaceIndent) {
                        [$line, $enum] = $colAttrWriter(reflectionModel: $reflectionModel, attribute: $att, mappings: $mappings, indent: $this->indent . $namespaceIndent, noHidden: $noHidden, optionalNullables: $optionalNullables, useEnums: $useEnums);
                        if (! empty($line)) {
                            $entry .= $line;
                            if ($enum) {
                                $enumReflectors[] = $enum;
                            }
                        }
                    });
                }

                if ($nonColumns->isNotEmpty()) {
                    $entry .= "{$this->indent}{$namespaceIndent}  // mutators" . PHP_EOL;
                    $nonColumns->each(function ($att) use (&$entry, $reflectionModel, $colAttrWriter, $noHidden, $optionalNullables, $mappings, $useEnums, &$enumReflectors, $namespaceIndent) {
                        [$line, $enum] = $colAttrWriter(reflectionModel: $reflectionModel, attribute: $att, mappings: $mappings, indent: $this->indent . $namespaceIndent, noHidden: $noHidden, optionalNullables: $optionalNullables, useEnums: $useEnums);
                        if (! empty($line)) {
                            $entry .= $line;
                            if ($enum) {
                                $enumReflectors[] = $enum;
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
                    $relations->each(function ($rel) use (&$entry, $relationWriter, $optionalRelations, $plurals, $namespaceIndent, $modelNamespaceMap, $groupName, &$crossNamespaceImports) {
                        // Use full class name if available to determine namespace
                        $relatedFullClass = $rel['relatedFullClass'] ?? null;
                        $relatedName = $rel['related'];
                        
                        if ($relatedFullClass) {
                            // Extract just the class name from full class
                            $relatedBaseName = $this->getClassName($relatedFullClass);
                            
                            // Find the matching model in our map by comparing full class names
                            $relatedGroup = null;
                            foreach ($modelNamespaceMap as $modelName => $info) {
                                if ($info['fullClass'] === $relatedFullClass) {
                                    $relatedGroup = $info['group'];
                                    break;
                                }
                            }
                            
                            // Check if it's in a different namespace
                            if ($relatedGroup && $relatedGroup !== $groupName) {
                                $relatedNamespace = $this->formatNamespaceName($relatedGroup);
                                $crossNamespaceImports[$relatedBaseName] = $relatedNamespace;
                                // Prefix the related model with its namespace
                                $rel['related'] = $relatedNamespace . '.' . $relatedBaseName;
                            }
                        } else {
                            // Fallback to original logic if we couldn't get full class name
                            if (isset($modelNamespaceMap[$relatedName]) && $modelNamespaceMap[$relatedName]['group'] !== $groupName) {
                                $relatedNamespace = $this->formatNamespaceName($modelNamespaceMap[$relatedName]['group']);
                                $crossNamespaceImports[$relatedName] = $relatedNamespace;
                                // Prefix the related model with its namespace
                                $rel['related'] = $relatedNamespace . '.' . $relatedName;
                            }
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
                $output .= $entry;
            }

            // Add enums for this namespace
            collect($enumReflectors)
                ->unique(fn (ReflectionClass $reflector) => $reflector->getName())
                ->each(function (ReflectionClass $reflector) use (&$output, $useEnums, $namespaceIndent) {
                    $output .= app(WriteEnumConst::class)($reflector, $this->indent . $namespaceIndent, false, $useEnums);
                });
            
            // Close namespace
            $output .= "}" . PHP_EOL . PHP_EOL;
            
            // Add cross-namespace imports at the top of the file
            if (!empty($crossNamespaceImports)) {
                $importStatements = '';
                $importedNamespaces = [];
                foreach ($crossNamespaceImports as $modelName => $fromNamespace) {
                    // Import the whole namespace, not individual models
                    if (!in_array($fromNamespace, $importedNamespaces)) {
                        // Find the group for this namespace
                        $targetGroup = null;
                        foreach ($modelNamespaceMap as $name => $info) {
                            if ($name === $modelName) {
                                $targetGroup = $info['group'];
                                break;
                            }
                        }
                        
                        if ($targetGroup) {
                            $filename = str_replace('.d.ts', '', $this->getFilenameForGroup($targetGroup));
                            $importStatements .= "import { {$fromNamespace} } from './{$filename}'" . PHP_EOL;
                            $importedNamespaces[] = $fromNamespace;
                        }
                    }
                }
                $output = $importStatements . PHP_EOL . $output;
            }

            // Add imports for this file (external libraries)
            collect($imports)
                ->unique()
                ->each(function ($import) use (&$output) {
                    $importTypeWithoutGeneric = Str::before($import['type'], '<');
                    $entry = "import { {$importTypeWithoutGeneric} } from '{$import['import']}'" . PHP_EOL;
                    $output = $entry . $output;
                });

            if ($global) {
                $output .= '  }' . PHP_EOL . '}' . PHP_EOL . PHP_EOL;
            }

            $filename = $this->getFilenameForGroup($groupName);
            $this->outputs[$filename] = substr($output, 0, strrpos($output, PHP_EOL));
        }

        return $this->outputs;
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
     * Generate filename for a group.
     *
     * @param  string  $groupName
     * @return string
     */
    protected function getFilenameForGroup(string $groupName): string
    {
        if ($groupName === 'models') {
            return 'models.d.ts';
        }

        // Replace directory separators with appropriate naming
        $filename = str_replace('/', '-', $groupName);
        return $filename . '.d.ts';
    }
    
    /**
     * Format namespace name for TypeScript.
     *
     * @param  string  $groupName
     * @return string
     */
    protected function formatNamespaceName(string $groupName): string
    {
        if ($groupName === 'models') {
            return 'Models';
        }
        
        // Convert to PascalCase for TypeScript namespace
        $parts = explode('/', $groupName);
        return collect($parts)
            ->map(fn($part) => Str::studly($part))
            ->implode('');
    }
    
    /**
     * Build a map of model names to their namespace groups.
     *
     * @param  array<string, Collection<int, SplFileInfo>>  $groupedModels
     * @return array<string, array>
     */
    protected function buildModelNamespaceMap(array $groupedModels): array
    {
        $map = [];
        
        foreach ($groupedModels as $groupName => $models) {
            foreach ($models as $model) {
                $class = app()->getNamespace() . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($model->getPathname(), app_path() . DIRECTORY_SEPARATOR)
                );
                
                $modelName = $this->getClassName($class);
                // Store both the group name and full class for better matching
                $map[$modelName] = [
                    'group' => $groupName,
                    'fullClass' => $class
                ];
            }
        }
        
        return $map;
    }
}