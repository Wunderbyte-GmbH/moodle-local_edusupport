@local @local_edusupport
Feature: Assigning the first level support of a course
  In order to have the right people answer support requests
  As somebody who manages a course
  I need to name who supports that course

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname |
      | manager1    | Mandy     | Manager  |
      | teacher1    | Tina      | Teacher  |
      | assistant1  | Alex      | Assist   |
      | student1    | Sally     | Student  |
    And the following "courses" exist:
      | fullname     | shortname |
      | Support area | SUP       |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | manager1   | SUP    | editingteacher |
      | teacher1   | SUP    | editingteacher |
      | assistant1 | SUP    | teacher        |
      | student1   | SUP    | student        |
    And the following "activities" exist:
      | activity | course | name          | intro           |
      | forum    | SUP    | Support forum | Ask us anything |
    And the following "local_edusupport > supportforums" exist:
      | forum         |
      | Support forum |

  Scenario: The menu entry leads to the assignment page
    Given I log in as "manager1"
    And I am on "Support area" course homepage
    When I navigate to "First level support" in current page administration
    Then I should see "Nobody is assigned yet"

  Scenario: Somebody without the right does not see the entry
    Given I log in as "student1"
    And I am on "Support area" course homepage
    Then "First level support" "link" should not exist

  @javascript
  Scenario: Assigning somebody updates the list without leaving the page
    Given I log in as "manager1"
    And I am on "Support area" course homepage
    And I navigate to "First level support" in current page administration
    And I should see "Nobody is assigned yet"
    When I click on "Assign first level support" "button"
    And I set the field "Assign first level support" to "Tina Teacher"
    And I click on "Save changes" "button" in the "Assign first level support" "dialogue"
    And I wait until the page is ready
    Then I should see "Tina Teacher"
    And I should not see "Nobody is assigned yet"

  @javascript
  Scenario: Taking the assignment away again
    Given the following "local_edusupport > supporters" exist:
      | user     | course |
      | teacher1 | SUP    |
    And I log in as "manager1"
    And I am on "Support area" course homepage
    And I navigate to "First level support" in current page administration
    And I should see "Tina Teacher"
    When I click on "Assign first level support" "button"
    And I click on "Tina Teacher" "autocomplete_selection"
    And I click on "Save changes" "button" in the "Assign first level support" "dialogue"
    And I wait until the page is ready
    Then I should see "Nobody is assigned yet"
    And I should not see "Tina Teacher"

  @javascript
  Scenario: Only teaching staff can be chosen
    Given I log in as "manager1"
    And I am on "Support area" course homepage
    And I navigate to "First level support" in current page administration
    When I click on "Assign first level support" "button"
    And I open the autocomplete suggestions list
    Then "Alex Assist" "autocomplete_suggestions" should exist
    And "Sally Student" "autocomplete_suggestions" should not exist

  Scenario: An administrator fills the first level from the course rights
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > eduSupport" in site administration
    And I click on "Fill first level support from course rights" "link"
    Then I should see "Support area"
    And I should see "Assign 3 people"
    When I click on "Assign 3 people" "button"
    Then I should see "3 people were assigned."
    And I should see "Every eligible person is already assigned."

  Scenario: The overview lists both levels with where they support
    Given the following "local_edusupport > supporters" exist:
      | user     | course |
      | teacher1 | SUP    |
    And the following "local_edusupport > supporters" exist:
      | user     |
      | manager1 |
    And I log in as "admin"
    When I navigate to "Plugins > Local plugins > All support users" in site administration
    Then I should see "Tina Teacher"
    And I should see "Support area"
    And I should see "Mandy Manager"
    And I should see "The whole platform"
