@core
Feature: Page displaying with secure layout
  In order to securely perform tasks
  As a student
  I need not to be able to exit the page using the header logo

  Background:
    # Get to the fixture page.
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "activities" exist:
      | activity   | name | intro                                                               | course | idnumber |
      | label      | L1   | <a href="../lib/tests/fixtures/securetestpage.php">Fixture link</a> | C1     | label1   |

  Scenario: Confirm that there is no header link
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    When I follow "Fixture link"
    Then I should see "Acceptance test site" in the "nav" "css_element"
    But "Acceptance test site" "link" should not exist

  Scenario: Confirm that the user name is displayed in the navbar without a link
    Given I log in as "admin"
    And the following config values are set as admin:
      | shownameinsecurelayout | 1 |
    And I am on "Course 1" course homepage
    When I follow "Fixture link"
    Then I should see "You are logged in as Admin User" in the "nav" "css_element"
    But "Logout" "link" should not exist

  Scenario: Confirm that custom menu appears
    Given I log in as "admin"
    And the following config values are set as admin:
      | allowcustommenuinsecurelayout | 1 |
      | custommenuitems | -This is a custom item\|/customurl/ |
    And I am on "Course 1" course homepage
    When I follow "Fixture link"
    Then I should see "This is a custom item" in the "nav" "css_element"
