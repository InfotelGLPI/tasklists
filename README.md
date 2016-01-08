# tasklists
Plugin Tasklists for GLPI

Ajout d'une gestion de tâches simples. Ce plugin permet d'ajouter dans GLPI, une interface pour saisir des tâches simples.
> * Peut être utilisé avec le collecteur de mail pour créer des tâches.

Adding a management of simple tasks. This plugin adds in GLPI, an interface to input simple tasks.
> * Can be used with mail collector to create tasks.

For use it with mail collector you must to modify "inc/rulemailcollector.class.php" file, into  "executeActions" fonction, into switch : switch ($action->fields["action_type"]), add a default case  : 

```
default:
   //plugins actions
   $executeaction = clone $this;
   $output = $executeaction->executePluginsActions($action, $output, $params);
   break;
```
