@mod @mod_adaptivequiz
Feature: Attempt an adaptive quiz
  In order to demonstrate what I know using the adaptive quiz strategy
  As a student
  I need to be able to attempt an adaptive quiz

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | teacher1 | John      | The Teacher | johntheteacher@example.com  |
      | student1 | Peter     | The Student | peterthestudent@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name                    |
      | Course       | C1        | Adaptive Quiz Questions |
    And the following "questions" exist:
      | questioncategory        | qtype     | name | questiontext    |
      | Adaptive Quiz Questions | truefalse | Q1   | First question  |
      | Adaptive Quiz Questions | truefalse | Q2   | Second question |
    And the following "core_question > Tags" exist:
      | question | tag    |
      | Q1       | adpq_1 |
      | Q2       | adpq_2 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Adaptive Quiz" to section "1"
    And I set the following fields to these values:
      | Name                         | Adaptive Quiz               |
      | Description                  | Adaptive quiz description.  |
      | Question pool                | Adaptive Quiz Questions (2) |
      | Starting level of difficulty | 1                           |
      | Lowest level of difficulty   | 1                           |
      | Highest level of difficulty  | 2                           |
      | Minimum number of questions  | 1                           |
      | Maximum number of questions  | 2                           |
      | Standard Error to stop       | 20                          |
      | Attempts allowed             | 1                           |
      | ID number                    | adaptivequiz1               |
    And I click on "Save and return to course" "button"
    And I log out

  @javascript
  Scenario: Attempt an adaptive quiz
    When I am on the "adaptivequiz1" "Activity" page logged in as "student1"
    And I press "Start attempt"
    Then I should see "First question"

  @javascript
  Scenario: A student cannot attempt an adaptive quiz if no more attempts are allowed
    Given I am on the "adaptivequiz1" "Activity" page logged in as "student1"
    And I press "Start attempt"
    And I click on "True" "radio" in the "First question" "question"
    And I press "Submit answer"
    And I click on "True" "radio" in the "Second question" "question"
    And I press "Submit answer"
    And I press "Continue"
    When I am on the "adaptivequiz1" "Activity" page
    Then "Start attempt" "button" should not be visible
    And I should see "No more attempts allowed at this activity"

  @javascript
  Scenario: Return to a started attempt
    Given the following "questions" exist:
      | questioncategory        | qtype     | name | questiontext    |
      | Adaptive Quiz Questions | truefalse | Q3   | Third question  |
      | Adaptive Quiz Questions | truefalse | Q4   | Fourth question |
    And the following "core_question > Tags" exist:
      | question | tag    |
      | Q3       | adpq_2 |
      | Q4       | adpq_3 |
    And I am on the "adaptivequiz1" "Activity" page logged in as "teacher1"
    And I click on "Settings" "link"
    And I set the following fields to these values:
      | Highest level of difficulty  | 3 |
      | Minimum number of questions  | 1 |
      | Maximum number of questions  | 3 |
    And I click on "Save and return to course" "button"
    And I log out
    When I am on the "adaptivequiz1" "Activity" page logged in as "student1"
    And I press "Start attempt"
    And I click on "True" "radio"
    And I press "Submit answer"
    And I am on the "adaptivequiz1" "Activity" page
    And I press "Start attempt"
    And I click on "True" "radio"
    And I press "Submit answer"
    Then I should see "Fourth question"
