# Strategy Chart Bug Fix Tasks

## Overview
The strategy chart currently has a hard-coded minimum of 0. If any costs are below 0, the minimum should be set to the nearest -5, similar to the Agile cost chart on the dashboard.

## Tasks

1. [x] **Analysis and Understanding**
   - [x] Review the current StrategyChart implementation in `app/Filament/Resources/StrategyResource/Widgets/StrategyChart.php`
   - [x] Examine how the Agile cost chart (`app/Filament/Widgets/AgileChart.php`) handles negative values
   - [x] Identify all places where chart minimum values are set

2. [ ] **Architecture Improvements**
   - [ ] Evaluate if a common utility function for calculating chart minimums would be beneficial
   - [ ] Consider creating a trait for charts that need dynamic minimum values
   - [ ] Determine if other charts might benefit from the same fix

3. [x] **Code Implementation**
   - [x] Add a `minValue` property to the StrategyChart class
   - [x] Modify the `getDatabaseData()` method to calculate the minimum value based on the data
   - [x] Implement logic to round negative values to the nearest -5 (e.g., -3 becomes -5, -7 becomes -10)
   - [x] Update the `getOptions()` method to use the calculated minimum value
   - [x] Update the AgileChart to handle minimum values with the same rounding logic, to round negative values to the nearest -5 

4. [ ] **Testing**
   - [ ] Create test data that includes negative costs
   - [ ] Verify that the chart displays correctly with negative values
   - [ ] Ensure the minimum value is correctly set to the nearest -5
   - [ ] Check that positive-only data still displays correctly

5. [x] **Documentation**
   - [x] Update any relevant documentation about the chart behavior
   - [x] Add comments to the code explaining the minimum value calculation
   - [ ] Document the fix in the project changelog if one exists

6. [ ] **Code Review and Quality Assurance**
   - [ ] Review the changes for any potential side effects
   - [ ] Ensure the code follows project coding standards
   - [ ] Verify that the fix works across different browsers and devices

7. [ ] **Deployment**
   - [ ] Test the fix in a development environment
   - [ ] Plan for deployment to production
   - [ ] Monitor the chart after deployment to ensure it's working as expected
