== Yet Another Synchronization Service (YASS) ==
================================================

Background and some formative discussion available at:

https://docs.google.com/document/d/147nJMIbJpVu8WNLcb9YvmgAR-Y-ISpwzk5bSAsZolGc/edit?hl=en_US

YASS is a data-synchronization framework. Participants in the framework are
called "replicas". Each replica (YASS_Replica) must have a mechanism for
storing data (YASS_DataStore) and for storing synchronization state
(YASS_SyncStore) and may have other configuration data.

== Replica Specifications ==

To instantiate a replica, one must create a replica specification
(conventionally referred to as $replicaSpec). The $replicaSpec is an array
with the following items

name          STRING    A stable, symbolic name
datastore     STRING 	The class which implements YASS_DataStore interface
			"Memory", "LocalizedMemory", "GenericSQL", "ARMS"
syncstore     STRING    The class which implements YASS_SyncStore interface
			"Memory", "LocalizedMemory", "GenericSQL", "ARMS"
is_active     BOOLEAN   Whether the replica is currently in use
is_joined     BOOLEAN   Whether the replica has gone through "join" process

== Creating Replicas ==

Replicas can be created in the following ways:
 * Declaratively using hook_yass_replicas
 * Imperatively using YASS_Engine::singleton()->createReplica, which
   saves the spec and instantiates a YASS_Replica in the current process
 * Imperatively using YASS_Engine::singleton()->updateReplicaSpec, which
   saves the spec but does NOT instantiate a YASS_Replica in the current
   process

== Entity Handling ==

The synchronization service is generally agnostic to the details of entity
data models -- the only important details are the fields of YASS_Entity
("entityType", "entityGuid", and "data"). The handling of the "data" field
depends on the data store, e.g.

 * For YASS_DataStore_Memory, the "entityType" has no special meaning, and the
   "data" is copied verbatim an in-memory datastore (hash-table/associative
   array).
 * For YASS_DataStore_GenericSQL, the "entityType" has no special meaning, and
   the "data" is stored in serialize()d format within a SQL TEXT/CLOB field.
 * For YASS_DataStore_ARMS, the "entityType" maps to a SQL table, and
   the "data" maps (generally speaking) to a single record in the SQL table.

Implementing SQL-based entity storage for ARMS/CiviCRM/Drupal encounters
some complexity -- for better or worse, CiviCRM and Drupal are
highly-customizable platforms, and this leads to impedence mismatch in their
entity data-models. A fully-functional synchronization solution must
include some data transformations.

Entity data transformations are implemented with the bidirectional,
batch-oriented YASS_Filter interface. YASS_Filters may be associated with a
replica, and they will be executed as part of YASS_DataStore::getEntities()
and YASS_DataStore::putEntities(). The goal of these filters is to convert
between some "internal" data model and the "global / normal / lingua-franca"
data model.

A key design and configuration issue is choosing which filters to apply to
each replica.

== Entity Data Model for ARMS/CiviCRM/Drupal ==

The basic idea is to equate SQL tables with YASS entityTypes. However, there
are some challenges to using SQL data as interchangable data (such as
foreign keys and per-site custom-data fields). For concrete descriptions of
the challenges and solutions, review the filter pipeline constructed by
YASS_Schema_CiviCRM::onBuildFilters(). However, to get a general feel for
how the data changes, consider these data examples:

EX: "John Doe" in the global/normal data model 
  [[Note: This is the data-representation exchanged between replicas.]]
  - entityType: civicrm_contact
  - entityGuid: 123456789abcdef
  - data:
    - first_name: John
    - last_name: Doe
    - display_name: John Doe
    - employer_id: defabc321654987
    - #custom [[NOTE: These are custom-data fields suitable for cross-site synchronization]]
      - status: current_student
      - hs_grad_year: 2012
    - #unknown [[NOTE: These are custom-data fields which are NOT suitable for synchronization]]
      - ex-1.example.com
        - 12: 5.5
        - 13: array(goalkeeper,midfielder)
        - 15: US:VA
      - ex-2.example.com
        - 11: 90
        - 12: 63.1

EX: "John Doe" in the internal data model (for ex-1.example.com)
  [[Note: This is the data-representation used within an ARMS replica --
  the goal of the filter-chain is to translate from the global data-model to
  the internal data model.]]
  [[Note: This is inspired by the CiviCRM API, but it's tighter and less
  forgiving -- for example, when CRUDing a custom-data field with
  html_type=multi-select|checkbox, the API uses a morass of encodings;
  this implementation uses only one format for reading AND writing
  regardless of HTML widget.]]

  - entityType: civicrm_contact
  - entityGuid: 123456789abcdef
  - guidMapper->toLocal(123456789abcdef): 5
  - data:
    - first_name: John
    - last_name: Doe
    - display_name: John Doe
    - employer_id: defabc321654987
    - custom_4: current_student
    - custom_7: 2012
    - custom_12: 5.5
    - custom_13: ^goalkeeper^midfielder^
    - custom_15: 1045

EX: "John Doe" in the SQL data model (for ex-1.example.com)
  [[Note: Custom-data fields are physically stored in separate tables even
  though they can logically be considered part of the contact entity.]]

  - civicrm_contact
    - id: 5
    - first_name: John
    - last_name: Doe
    - display_name: John Doe
    - employer_id: 9
  - civicrm_value_main_4
    - id: 1234
    - entity_id: 5
    - status_4: current_student
    - high_school_graduation_7: 2012
    - positions_13: ^goalkeeper^midfielder^
    - birthplace_15: 1045
  - civicrm_value_stats_7:
    - id: 23
    - entity_id: 5
    - somestat_12: 5.5

