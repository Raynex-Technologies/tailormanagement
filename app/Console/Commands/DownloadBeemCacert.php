<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DownloadBeemCacert extends Command
{
    protected $signature = 'beem:download-cacert';

    protected $description = 'Download CA certificate bundle for Beem SMS API SSL verification (fixes "unable to get local issuer certificate" on Windows)';

    protected const CACERT_URL = 'https://curl.se/ca/cacert.pem';

    public function handle(): int
    {
        $path = storage_path('ssl/cacert.pem');
        $dir = dirname($path);

        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true)) {
                $this->error("Could not create directory: {$dir}");

                return self::FAILURE;
            }
        }

        $this->info('Downloading CA bundle from ' . self::CACERT_URL . ' ...');

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->get(self::CACERT_URL);

            if (! $response->successful()) {
                $this->error('Download failed: HTTP ' . $response->status());

                return self::FAILURE;
            }

            $contents = $response->body();
            if (strlen($contents) < 1000 || ! str_contains($contents, '-----BEGIN CERTIFICATE-----')) {
                $this->error('Downloaded content does not look like a valid CA bundle.');

                return self::FAILURE;
            }

            if (file_put_contents($path, $contents) === false) {
                $this->error("Could not write file: {$path}");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Download failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("CA bundle saved to: {$path}");
        $this->info('Beem SMS requests will use this bundle for SSL verification (no .env change needed if BEEM_VERIFY_SSL is not set).');

        return self::SUCCESS;
    }
}
