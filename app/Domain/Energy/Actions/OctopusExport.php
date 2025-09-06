<?php

declare(strict_types=1);

namespace App\Domain\Energy\Actions;

use App\Domain\Energy\Models\OctopusExport as OctopusExportModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\Actions\ActionResult;
use App\Support\Actions\Contracts\ActionInterface;

class OctopusExport implements ActionInterface
{
    /**
     * @deprecated Use execute() returning ActionResult instead.
     */
    public function run()
    {
        $this->execute();
    }

    /**
     * @throws \Throwable
     */
    public function execute(): ActionResult
    {
        try {
            Log::info('Start running Octopus export action');

            $result = OctopusExportModel::query()
                ->latest('interval_start')
                ->first('interval_start');

            $lastExportStart = $result ? $result->interval_start : now()->subDays(2);

            throw_if(
                $lastExportStart >= now()->subDay(),
                sprintf(
                    'Last updated in the day, try again in %s',
                    $lastExportStart->addDay()->diffForHumans()
                )
            );

            // fetch the latest export data
            $data = $this->getExportData();

            // save it to the database
            OctopusExportModel::upsert(
                $data,
                uniqueBy: ['interval_start'],
                update: ['consumption']
            );

            return ActionResult::success(['records' => count($data)], 'Octopus export updated');
        } catch (\Throwable $e) {
            Log::warning('OctopusExport failed', ['exception' => $e->getMessage()]);
            return ActionResult::failure($e->getMessage());
        }
    }

    /**
     * @throws \Throwable
     */
    private function getExportData()
    {
        $api = Config::get('octopus.api_key');
        $exportMan = Config::get('octopus.export_mpan');
        $exportSerialNumber = Config::get('octopus.export_serial_number');

        $url = sprintf(
            'https://api.octopus.energy/v1/electricity-meter-points/%s/meters/%s/consumption?page_size=200',
            $exportMan,
            $exportSerialNumber,
        );

        try {
            $response = Http::withBasicAuth($api, '')->get($url);
        } catch (ConnectionException $e) {
            Log::error('There was a connection error trying to get Octopus export data:' . $e->getMessage());
            throw new \RuntimeException('There was a connection error trying to get Octopus export data:'
                . $e->getMessage());
        }

        $data = $response->json();
        Log::info(
            'Octopus export action',
            [
                'successful' => $response->successful(),
                'json' => $data,
            ]
        );

        throw_if($response->failed(), 'Unsuccessful Octopus export, check the log file for more details.');

        return collect($data['results'])
            ->map(function ($item) {
                return [
                    // "consumption":0.001,"interval_start":"2024-06-15T00:00:00+01:00","interval_end":"2024-06-15T00:30:00+01:00"
                    'consumption' => $item['consumption'],
                    'interval_start' => Carbon::parse($item['interval_start'])->timezone('UTC')->toDateTimeString(),
                    'interval_end' => Carbon::parse($item['interval_end'])->timezone('UTC')->toDateTimeString(),
                ];
            })->toArray();
    }
}
