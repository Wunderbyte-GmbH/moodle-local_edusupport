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

  @javascript
  Scenario: The state filter hides the issues that do not match
    Given I log in as "supporter1"
    And I visit "/local/edusupport/issues.php"
    And I should see "Printer is broken"
    When I set the field "Closed" to "1"
    Then I should not see "Printer is broken"
    When I set the field "Closed" to ""
    Then I should see "Printer is broken"

  @javascript
  Scenario: A supporter changes the status of an issue from the issue page
    Given I log in as "supporter1"
    And I visit "/local/edusupport/issues.php"
    And I click on "Printer is broken" "link"
    When I set the field "Change status" to "Ongoing"
    And I wait until the page is ready
    And I visit "/local/edusupport/issues.php"
    # The state filter is the oracle here: the issue must have left "Not yet started"
    # and arrived at "Ongoing". Asserting on the label alone would pass either way.
    And I set the field "Not yet started" to "1"
    Then I should not see "Printer is broken"
    When I set the field "Not yet started" to ""
    And I set the field "Ongoing" to "1"
    Then I should see "Printer is broken"

  Scenario: An administrator picks the support forum of a course
    Given the following "activities" exist:
      | activity | course | name             | intro          |
      | forum    | SUP    | Second forum     | Not for support |
    And I log in as "admin"
    And I am on "Support area" course homepage
    When I navigate to "Choose forums for eduSupport" in current page administration
    Then I should see "Second forum"
    And I should see "Course Supportforum"
    When I click on "enable" "link" in the "Second forum" "table_row"
    Then I should see "disable" in the "Second forum" "table_row"
    When I click on "disable" "link" in the "Second forum" "table_row"
    Then I should not see "disable" in the "Second forum" "table_row"

  Scenario: The person handling an issue links to their profile
    Given the following "users" exist:
      | username  | firstname | lastname |
      | platform1 | Paula     | Platform |
    And the following "local_edusupport > supporters" exist:
      | user      |
      | platform1 |
    And the following "local_edusupport > issues" exist:
      | forum         | user     | subject        | supporter  |
      | Support forum | student1 | Mouse is stuck | supporter1 |
    # Somebody other than the assigned person looks at the issue, so the name on the page
    # can only be the link to the assigned person.
    And I log in as "platform1"
    And I visit "/local/edusupport/issues.php"
    And I click on "Mouse is stuck" "link"
    When I click on "Sam Support" "link"
    Then I should see "User details"
    And I should see "Sam Support"
