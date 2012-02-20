== Yet Another Synchronization Service (YASS) ==
================================================

YASS is a data-synchronization framework for PHP. Participants in the
framework are called "replicas". Each replica (YASS_Replica) must have a
mechanism for storing data (YASS_IDataStore) and for storing synchronization
state (YASS_ISyncStore) and may have other configuration data. It currently
supports synchronization across CiviCRM databases.

== Code Layout ==

YASS is primarily designed and implemented using object-oriented
techniques -- the relevant classes and interfaces are in the "YASS"
directory.

To facilitate deployment, various Drupal modules are also included.
Depending on the role of a Drupal site in the replica system, one would
activate a different module. For example, to create a star/hub topology, one
might activate "yass_replica_arms" on each satellite and then, on the hub,
activate "yass_replica_master" and "yass_replica_interlink".
