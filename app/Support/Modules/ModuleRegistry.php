<?php

namespace App\Support\Modules;

/**
 * Lightweight module registry for the Enterprise Hub plugin architecture.
 *
 * Each module under app/Modules/<Name>/ ships a `module.json` manifest. This
 * registry discovers the enabled ones so a single ModulesServiceProvider can
 * boot them — adding a module is "drop a folder + manifest", no core edits.
 */
class ModuleRegistry
{
    /**
     * Decoded manifests for every enabled module, each with a `__dir` key
     * pointing at the module directory.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function manifests(): array
    {
        $modules = [];

        foreach (glob(app_path('Modules/*/module.json')) ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data) && ($data['enabled'] ?? false) === true) {
                $data['__dir'] = dirname($file);
                $modules[] = $data;
            }
        }

        return $modules;
    }

    /**
     * Fully-qualified service-provider class for each enabled module.
     *
     * @return array<int,class-string>
     */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $manifest) => $manifest['provider'] ?? null,
            self::manifests(),
        )));
    }
}
