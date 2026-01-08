# User Requests

This is a list of user requests and bug reports.

The requests should be noted, reviewed and added to the [task list](tasks.md).

## Bugs

### Incorrect title on Solcast charts

On the Solcast vs actual forecasts chart: Solis should be corrected to Solcast.

- Current action: Added as task 1.1.2 in `docs/tasks.md`
- Status: Complete

## Features

### Strategies page

#### Make the 'Forecast for ...' chart interactive.

- Add a label for each period. Currently, each item in a period is a value.
- Add a current period indicator, e.g. a line or a different background colour
- Enable zooming and panning.
- Extend the start time to 7pm the previous day.
- Values after 22:30 the current day, when the import value is null, are shown as 0.
    - Keep as null and don't show the value or calculate a 'Net cost (Import - Export - 15)'
- Current action: Add a task to section 1.1.3 of  `docs/tasks.md`
- Status: Complete

#### Improve the strategy algorithm

Currently, when the generate strategy button is pressed, the strategy (strat1) is true or false, based on a threshold
under an average cost for a day.

1. Update the strategy to be the best recommended strategy based on two periods (see below), which will allow a
   maximum of three charge sessions per period, based on the cheapest charge strategy
2. If the 'strat' field hasn't been set (is false for the period), also update 'strat' to match 'strat1'.

Periods:

- 16:00 to 23:00,
    - it is possible the battery will still be charged, or the evening rate is not inexpensive, so no top up is
      required, especially if there is no cost saving
- 23:00 to 16:00
    - historically, overnight is normally the cheapest time to top up the battery, before costs rise early morning.
    - the battery should be full by 4pm, ready for the expensive period 4pm to 7pm.

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### Improve the strategy charts

The strategy charts currently display the current day, it should be extended to display from 4pm
the previous day to 4pm (16:00) GMT the current day:

- `app/Filament/Resources/StrategyResource/Widgets/CostChart.php`
- `app/Filament/Resources/StrategyResource/Widgets/ElectricImportExportChart.php`
- `app/Filament/Resources/StrategyResource/Widgets/StrategyChart.php`

Change the drop-down to display the period start and end times.

- Current action: Added as task 1.1.6 of  `docs/tasks.md`
- Status: Completed

#### Update the strategy generator

After updating the charts, the strategy needs to generate the correct consumption data for the period from 16:00 the
previous day to 16:00 the current day

- Current action: Add a task to section 1.1.7 of  `docs/tasks.md`
- Status: Completed

#### Add a way to compare strategy based on consumption from last week and three weeks average

The original idea was to have a button or toggle, so the calculate battery button, which re-runs the strategy and
displays the forecast chart, can compare with last week's data or an average over three weeks.

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### The battery cost is sometimes negative when full.

When the battery is full, it shows a negative value. This is maybe a bug on
the 'Forecast for'. chart.

- Current action: Add a task to section 1.1.10 of  `docs/tasks.md`
- Status: Complete

#### Helper to export strategy

There is no API for Solis cloud, the website requires a login and update a form on the Inverter section. The charge
start time and end time for each period must be manually entered.

Investigate options on how to accurately update the Solis inverter.

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

### Dashboard

#### Make the Agile cost chart interactive

- Add a label for each period. Currently, each item in a period is a label value.
    - Instead, when the cursor is over the period, display a label box with all the values of the items in that
      period.
- Add a current period indicator, e.g. a line or a different background colour
- Enable zooming and panning
- Current action: Added as task 1.1.3 of  `docs/tasks.md`
- Status: Complete

### General

#### Add CI badges to the README.md

- GitHub Action CI has been added to check code coverage, add a coverage and passing badge for tests and
  code-quality badge too.
- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### Fix CI upload code coverage to codecov

The CI to add code coverage is displaying the following error:

```text
[2025-11-23T13:55:39.123Z] ['info'] Detected GitHub Actions as the CI provider.
[2025-11-23T13:55:39.377Z] ['info'] Pinging Codecov: https://codecov.io/upload/v4?package=github-action-3.1.6-uploader-0.8.0&token=*******&branch=bugfix%2Fcorrect-solcast-charts&build=19612205492&build_url=https%3A%2F%2Fgithub.com%2FPen-y-Fan%2Fsolar%2Factions%2Fruns%2F19612205492%2Fjob%2F56159668127&commit=544266e781a4f16e8fd6d664efc20a70d7a2d5c6&job=CI&pr=60&service=github-actions&slug=Pen-y-Fan%2Fsolar&name=&tag=&flags=&parent=
[2025-11-23T13:55:39.665Z] ['error'] There was an error running the uploader: Error uploading to https://codecov.io: Error: There was an error fetching the storage URL during POST: 429 - {"message":"Rate limit reached. Please upload with the Codecov repository upload token to resolve issue. Expected time to availability: 3045s."}
```

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### Setting the environment to production breaks login

Filament does not allow a user to log in when the project is deployed and the environment is set to production.

- Every user is shown a 403 page, they are not able to log in.
    - The guest '/welcome' page can be viewed.
- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### The CI code-quality check isn't consistent

The CI is failing code-quality PHP Code Sniffer `vendor/bin/phpcs --standard=PSR12 app tests` is different from the
`composer cs` which is `phpcs --standard=PSR12 --extensions=php app tests`. CI it is checking code in tests, it is
failing for .js' code being incorrectly formated. Update the commands so they are consistent. Recommend both use
`--extensions=php,js` for constancy

- 'PHPCBF CAN FIX THE nn MARKED SNIFF VIOLATIONS AUTOMATICALLY', so after updating the config, run `composer cs-fix`

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

#### The CI build for tests is failing

When building the CI fails to complete the build, probably due to a missing step `npm run build`

`Run php artisan key:generate` fails:

Illuminate\Foundation\ViteManifestNotFoundException

- Current action: Added a task to section 1.1.5 of  `docs/tasks.md` and completed
  `docs/monthly-large-dataset-performance-checks.md`
- Status: Complete

##### Monthly large dataset performance checks

This is an automated monthly reminder to run the Large dataset advisory performance checks locally and paste summarized
metrics into `docs/performance-testing.md` under "Large Dataset Advisory Results (Local)".

Checklist:

- Re-seed Large: `PERF_DATASET_SIZE=large php artisan migrate:fresh --seed --seeder=PerformanceSeeder`
- Run k6 suite (local):
  `APP_URL=https://solar-dev.test VUS=8 DURATION=90s PERF_DATASET_SIZE=large bash tests/Performance/run-all.sh`
- Summarize p95/RPS/error rate per scenario in the doc (do not commit artifacts).
- Note: Keep feature caches and downsampling flags OFF unless explicitly profiling.
- Current action: Added a task to section 1.1.4 of  `docs/tasks.md` and completed
  `docs/monthly-large-dataset-performance-checks.md`
- Status: Complete (for Dec)

## Tariff

### Compare the Octopus tariffs

Compare the current Octopus tariff with other available based on sample consumption and PV generation.

- The comparison should calculate a week's worth of cost for each available domestic Octopus tariff, display graphs per
  day and a total for the week, based on the usage from the previous week.
- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started

### Solcast Forecast vs Actual Forecast vs Real PV comparison

We are using the Solcast Forecast API for PV to create a strategy for when to charge the battery, based on the cost to
charge and what the expected forecast PV will be tomorrow. The expected forecast needs to be accurate, although it will
always be a forecast.

#### Definition

- Solcast Forecast API: what Solcast expected the forecast PV to be for the next few days.
- Solcast Actual Forecast API: What Solcast actually thinks the PV we received was
- Real PV: The real PV generated from the solar panels via the Solis inverter

#### Notes

- I have observed the Solcast actual data on a clear, bright day, started and ended later than real PV.
- The Solcast data is based on long, lat, tilt, azimuth and capacity. It does not take terrain into account.
- I have trees to the west which are higher than the horizon, which affects run set. In the autumn, winter and spring
  trees to the south-west also block direct sunlight, when the sun doesn't rise high.

#### Recommended actions

- Confirm the Solcast API UTC data isn't being double shifted to BST in the summer.
- Consider creating an algorithm estimator to convert Solcast forecast to real PV. Then calibrate using a nice day from
  each season to allow for terrain.

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started
