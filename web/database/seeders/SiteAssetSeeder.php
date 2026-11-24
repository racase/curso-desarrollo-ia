<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SiteAssetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            for ($siteNumber = 1; $siteNumber <= 6; $siteNumber++) {
                $suffix = sprintf('%02d', $siteNumber);
                $site = Site::firstOrCreate(['code' => 'S'.$suffix], ['name' => 'Sede '.$suffix]);
                if ($site->name !== 'Sede '.$suffix) {
                    throw new RuntimeException('Catálogo inesperado: nombre de sede '.$site->code);
                }
                for ($assetNumber = 1; $assetNumber <= 5; $assetNumber++) {
                    $assetSuffix = sprintf('%02d', $assetNumber);
                    $asset = Asset::firstOrCreate(['code' => $site->code.'-A'.$assetSuffix], [
                        'name' => 'Activo '.$assetSuffix, 'site_id' => $site->id,
                    ]);
                    if ($asset->site_id !== $site->id || $asset->name !== 'Activo '.$assetSuffix) {
                        throw new RuntimeException('Catálogo inesperado: pertenencia o nombre de activo '.$asset->code);
                    }
                }
            }
            if (Site::count() !== 6 || Asset::count() !== 30 || Site::has('assets', '!=', 5)->exists()) {
                throw new RuntimeException('Catálogo inesperado: se requieren exactamente 6 sedes y 5 activos por sede (30 en total). No se han borrado datos.');
            }
        });
    }
}
