<x-filament-panels::page>
    <div class="fi-page-content">
        <x-filament::card>
            <div class="prose dark:prose-invert max-w-none">
                <h2>Database Backup Management</h2>
                <p>
                    Click the <strong>Generate & Download Backup</strong> button at the top right of this page to immediately generate a complete SQL dump of your database, which will be securely packaged into a ZIP file and downloaded to your computer.
                </p>
                
                <div class="mt-4 p-4 bg-primary-50 dark:bg-primary-900/30 rounded-lg text-primary-600 dark:text-primary-400">
                    <h3 class="flex items-center gap-2 m-0 text-lg">
                        <x-filament::icon icon="heroicon-m-information-circle" class="h-5 w-5" />
                        Important Note
                    </h3>
                    <p class="mt-2 mb-0">
                        Please keep your downloaded ZIP files in a secure location. They contain sensitive patient and clinic data. Regular backups are strongly recommended to prevent data loss.
                    </p>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
