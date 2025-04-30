@tool @tool_murelation @javascript @MuTMS
Feature: Site managers can manage frameworks for user relations
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "role assigns" exist:
      | user      | role          | contextlevel | reference |
      | manager1  | manager       | System       |           |

  Scenario: Create, update and delete user relation framework as manager
    Given I log in as "manager1"
    And I navigate to "Users > Supervisors and teams > User relation frameworks" in site administration
