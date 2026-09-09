@local @local_edusupport
Feature: Handling a support issue from creation to closing
  In order to keep track of support requests
  As a supporter
  I need to see, react to, close and reopen issues consistently on every page

  Background:
    Given the following "users" exist:
      | username   | firstname | lastname |
      | supporter1 | Sam       | Support  |
      | student1   | Sally     | Student  |
    And the following "courses" exist:
      | fullname     | shortname |
      | Support area | SUP       |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | supporter1 | SUP    | editingteacher |
      | student1   | SUP    | student        |
    And the following "activities" exist:
      | activity | course | name          | intro          |
      | forum    | SUP    | Support forum | Ask us anything |
    And the following "local_edusupport > supportforums" exist:
      | forum         |
      | Support forum |
    And the following "local_edusupport > supporters" exist:
      | user       |
      | supporter1 |
    And the following "local_edusupport > issues" exist:
      | forum         | user     | subject             |
      | Support forum | student1 | Printer is broken   |

  Scenario: A supporter sees an open issue in the issue list
    Given I log in as "supporter1"
    When I visit "/local/edusupport/issues.php"
    Then I should see "Printer is broken"

  Scenario: Closing an issue from the issue list marks it as closed
    Given I log in as "supporter1"
    And I visit "/local/edusupport/issues.php"
    When I click on "close issue" "link"
    Then I should see "🔒 Printer is broken"

  Scenario: A closed issue can be reopened from the issue list
    Given I log in as "supporter1"
    And I visit "/local/edusupport/issues.php"
    And I click on "close issue" "link"
    And I should see "🔒 Printer is broken"
    When I click on "reopen" "link"
    Then I should see "Printer is broken"
    And I should not see "🔒 Printer is broken"

  Scenario: Someone outside the support team cannot see the issue list
    Given I log in as "student1"
    When I visit "/local/edusupport/issues.php"
    Then I should see "Missing required permission"
    And I should not see "Printer is broken"

  @javascript
  Scenario: Closing an issue from the issue page marks it the same way
    Given I log in as "supporter1"
    When I visit "/local/edusupport/issues.php"
    And I click on "Printer is broken" "link"
    And I click on "Close issue" "link"
    Then I should see "🔒 Printer is broken"

  @javascript
  Scenario: A user files a support request through the support form
    Given I log in as "student1"
    And I am on "Support area" course homepage
    When I click on "Support" "link"
    And I set the field "faqread" to "1"
    And I set the field "Subject" to "My screen stays black"
    And I set the field "Describe the problem encountered including the link to the page/course where the problem occured" to "Nothing happens when I log in."
    And I click on "Contact support" "button"
    Then I should see "Success"
