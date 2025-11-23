# User Requests

This is a list of user requests and bug reports.

The requests should be noted, reviewed and added to the [task list](docs/tasks.md).

## Bugs

- On the Solcast vs actual forecasts chart: Solis should be corrected to Solcast.
    - Current action: Added as task 1.1.2 in `docs/tasks.md`
    - Status: Complete

## Features

### Strategies page

- Make the 'Forecast for ...' chart interactive.
    - Add a label for each period. Currently, each item in a period is a value.
    - Add a current period indicator, e.g. a line or a different background colour
    - Enable zooming and panning.
    - Extend the start time to 7pm the previous day.
    - Values after 22:30 the current day, when the import value is null are shown as 0.
        - Keep as null and don't show the value or calculate a 'Net cost (Import - Export - 15)'
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

- Improve the generate strategy button.
    - Currently the recommended strategy (strat1), is based on an average cost. Update it to be the best recommended
      strategy based on three periods, which will allow a maximum of 3 charge sessions, based on the cheapest strategy:
        - 7pm to 11pm
        - 11pm to 8am
        - 8am to 4pm, the battery should be full by then, ready for the expensive period 4pm to 7pm.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

- There is a Copy consumption from last week button.
    - Add a fields for the average consumption over three weeks. I believe the logic is already there.
    - The battery cost isn't always correct. When the battery is full, it shows a negative value. This maybe a bug on
      the 'Forecast for ..'. chart.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

- Export the strategy, for a period, to help update the charging schedule in Solis.
    - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
    - Status: Not started

### Dashboard

- Make the Agile costs from.. chart interactive.
    - Add a label for each period. Currently, each item in a period is a label value.
        - Instead, when the cursor is over the period, display a label box with all the values of the items in that
          period.
    - Add a current period indicator, e.g. a line or a different background colour
    - Enable zooming and panning.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

### General

- Add CI badges to the README.md
    - GitHub Action CI has been added to check code coverage, add a coverage and passing badge for tests, and
      code-quality badge too.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

- Filament does not allow a user to login when the project is deployed and the environment is set to production.
    - Every user is shown a 403 page, they are not able to login.
        - The guest  '/welcome' page can be viewed.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

- The CI is failing code-quality PHP Code Sniffer `vendor/bin/phpcs --standard=PSR12 app tests` is different from the
  `composer cs` which is `phpcs --standard=PSR12 --extensions=php app tests`. CI it is checking code in tests, it is
  failing for .js' code being incorrectly formated. Update the commands so they are consistent. Recommend both use
  `--extensions=php,js` for constancy
    - 'PHPCBF CAN FIX THE nn MARKED SNIFF VIOLATIONS AUTOMATICALLY', so after updating the config, run `composer cs-fix`
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

## Tariff

- Compare the Octopus tariffs current vs available based on sample consumption and PV generation.
    - The comparison should calculate a weeks worth of cost of each available domestic Octopus tariff, display graphs per day and a total for the week, based on the usage from the previous week.
  - Current action: Add a task to section 1.1.x of  `docs/tasks.md`
  - Status: Not started

## Solcast Forecast vs Actual Forecast vs Real PV comparison

We are using the Solcast Forecast API for PV, to create a strategy for when to charge the battery, based on the cost to charge and what the expected forecast PV will be tomorrow. The expected forecast therefore need to be reasonable accurate, although it will always be a forecast.

### Definition

- Solcast Forecast API: what Solcast expected the forecast PV to be for the next few days.
- Solcast Actual Forecast API: What Solcast actually thinks the PV we received was
- Real PV: The real PV generated from the solar panels via the Solis inverter

### Notes

- I have observed the Solcast actual data on a clear, bright day, started and ended later than real PV.
- The Solcast data is based on long, lat, tilt, azimuth and capacity. It does not take terrain into account.
- I have trees to the west which are higher than the horizon, which affects run set. In the autumn, winter and spring
  trees to the south-west also block direct sunlight, when the sun doesn't rise high.

### Recommended actions

- Confirm the Solcast API UTC data isn't being double shifted to BST in the summer.
- Consider creating an algorithm estimator to convert Solcast forecast to real PV. Then calibrate using a nice day from each season to allow for terrain.

- Current action: Add a task to section 1.1.x of  `docs/tasks.md`
- Status: Not started
