== Yet Another Synchronization Service (YASS) ==
================================================

Background and some formative discussion available at:

https://docs.google.com/document/d/147nJMIbJpVu8WNLcb9YvmgAR-Y-ISpwzk5bSAsZolGc/edit?hl=en_US

YASS is a data-synchronization framework. Participants in the framework are
called "replicas". Each replica (YASS_Replica) must have a mechanism for
storing data (YASS_IDataStore) and for storing synchronization state
(YASS_ISyncStore) and may have other configuration data.

== Replica Specifications ==

To instantiate a replica, one must create a replica specification
(conventionally referred to as $replicaSpec). The $replicaSpec is an array
with the following items

name          STRING    A stable, symbolic name
datastore     STRING 	The class which implements YASS_IDataStore interface
			"Memory", "LocalizedMemory", "GenericSQL", "CiviCRM",
			"Proxy"
syncstore     STRING    The class which implements YASS_ISyncStore interface
			"Memory", "LocalizedMemory", "GenericSQL", "CiviCRM",
			"Proxy"
guid_mapper   STRING    The class which implements YASS_IGuidMapper interface
                        "GenericSQL", "Proxy"
is_active     BOOLEAN   Whether the replica is currently in use
is_joined     BOOLEAN   Whether the replica has gone through "join" process
is_triggered  BOOLEAN   Whether the replica uses SQL triggers
access_control BOOLEAN	Whether the replica has built-in ACL support
			(Requires GenericSQL)

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
 * For YASS_DataStore_CiviCRM, the "entityType" maps to a SQL table, and
   the "data" maps (generally speaking) to a single record in the SQL table.
 * For YASS_DataStore_Proxy, the data is passed to a remote YASS system
   via arms_interlink.

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
    - #unknown [[NOTE: These are custom-data fields which are NOT suitable for cross-site
      synchronization]]
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

== Entity Security ==

A key requirement for YASS deployment is to allow different replicas to
store different combinations of entities -- one replica, for example,
might store only entities relevant to "men's baseball" while another
replica stores only entities relevant to "active students".

To be meaningfully enforced, security is generally implemented in the
"master" runtime rather than each individual site's runtime -- i.e. security
logic is associated with instances of YASS_DataStore_Proxy instead
of YASS_DataStore_CiviCRM.

Entity security consists of three main parts:

 1. The entity data-model must include any keys required for security
    policies. This can be done by extending the CiviCRM data
    model. Alternatively, if some key is implicit in the identity of the
    site (e.g. all records on the men's baseball site implicitly
    have gender=men,sport=baseball), then you can define constants for
    that replica. (e.g. using YASS_Filter_Constants.)

 2. The master replica hides certain entities using an access-control list.
    The hiding mechanism is deeply integrated with the master replica
    (YASS_DataStore_GenericSQL and YASS_SyncStore_GenericSQL). Specifically,
    when saving an entity to the master (via
    YASS_DataStore_GenericSQL::putEntities) one should pass in a whitelist
    of approved replicas using the "#acl" field. When reading and
    synchronizing entities (YASS_SyncStore_GenericSQL::getModified;
    YASS_DataStore_GenericSQL::getEntities), the results are filtered based
    on the "#acl" field.

    Note: To determine whether it can safely return an entity, the master
    replica needs to identify its synchronization partner. This cannot
    be determined using the DataStore contract -- when access-control is
    active, the replica has an unseen dependency on the
    YASS_Context::get('pairing').

 3. The entity data-model may include different fields on different sites;
    for example, on a compliance site, the contact entity may have a
    "cumulative GPA" field; on a volleyball, the same entity may
    instead have a "standing reach" field. All these fields are
    supported and stored on the master replica, but they do not propagate
    any further. The propagation can be controlled with filters
    (eg YASS_Filter_StdColumns).
    
    Note: These filters are should be bound to the proxy replicas; it
    would be less secure to bind security filters to the underlying
    CiviCRM-local replicas.

== Entity Existence ==

(FIXME: Cleanup; better prose)

There are a few cases in which one may try to work with entities that don't
(appear to) exist -- i.e. cases where the synchronization system creates
objects even though there is no underling data. Specifically:

|| Case                 || YASS_Entity          || YASS_SyncState
||                      || (YASS_DataStore)     || (YASS_SyncStore)
||======================||======================||==================
|| Deleted entity       || Yes (tombstone)      || Maybe
|| Unauthorized entity  || Yes (tombstone)      || Maybe
|| Invalid GUID         || Yes (tombstone)      || No

In all three-cases, YASS_DataStore::getEntities() will return a tombstone --
i.e. an instance of YASS_Entity with exists==FALSE, data==FALSE, and
entityType==FALSE. This tombstone can traverse the full filter chain, e.g.

	getEntities => Filter #1 => Filter #2 => ... => putEntities

If one passes a tombstone into YASS_DataStore::putEntities(), the datastore
should delete its copy of the entity. (Aside: Recall that the datastore
interface is generally dumb -- when the sync service instructs the data
store to delete an entity, that's all it needs to do. It doesn't need
to maintain syncstates.)

When it comes to syncstates, there is some variation in how non-existent
entities may appear. The general intent is to ensure that, whenever
something interesting happens to an entity (modification, deletion,
deauthorization), the YASS_SyncStore::getModified() should be able
to report that to interested parties -- regardless of whether the
entity currently has any content.

Generally, if a replica has previously seen an entity which is subsequently
deleted or deauthorized, then that replica should have syncstate for the
non-existent entity, and it should receive notifications about the
non-existent entity. However, if the replica has never had the entity (esp.
if the GUID has never been valid or if it's always been hidden), then it
should not have a syncstate for the entity.

The "is or ever was" criterion is tricky. The master can determine this
by inspecting its access-control entries: 

	Never authorized		No instance of yass_ace exists
	Currently authorized		yass_ace exists with is_allowed==1
	Previously authorized		yass_ace exists with is_allowed==0

