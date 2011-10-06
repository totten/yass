<?php

// Generate a series of synchronization scenarios which combine entity-update and replica-sync operations.

if(!yassgen_isCli()) {
  printf("This must be invoked via command-line.\n");
  exit();
}

$moves = array(
  'update_r1',
  'update_r2',
  'update_r3',
  'sync_r1', // r1=>m + m=>r1
  'sync_r2', // r2=>m + m=>r2
  'sync_r3', // r2=>m + m=>r2
);

$sentences = yassgen_combinations($moves, ' ', 7, 'add_r1 sync_r1');
$sentences = array_filter($sentences, 'yassgen_syncbeforeedit');
usort($sentences, 'yassgen_revlen');
// print_r($sentences);

$keepers = array();
foreach ($sentences as $sentence) {
  $sentence = trim($sentence, ':');
  // only want sentences with (r1+r2) or (r1+r2+r3) ops; skip, eg, (r2+r3) which is equivalent to (r1+r2)
  $replicas = 0;
  if (FALSE !== strpos($sentence, '_r1')) {
    $replicas += 1;
  }
  if (FALSE !== strpos($sentence, '_r2')) {
    $replicas += 2;
  }
  if (FALSE !== strpos($sentence, '_r3')) {
    $replicas += 4;
  }
  if (!in_array($replicas, array(3,7))) {
    continue;
  }
  
  // only want permutations with at least one update
  if (FALSE === strpos($sentence, 'update_')) continue;
  
  // sync'ing as the first step is pointless
  if (0 === strpos($sentence, 'sync_')) continue;
  
  // only want permutations for which each mentioned replica has a sync
  if (FALSE !== strpos($sentence, '_r1') && FALSE === strpos($sentence, 'sync_r1')) continue;
  if (FALSE !== strpos($sentence, '_r2') && FALSE === strpos($sentence, 'sync_r2')) continue;
  if (FALSE !== strpos($sentence, '_r3') && FALSE === strpos($sentence, 'sync_r3'))  continue;
  
  // don't do duplicate operations
  if (FALSE !== strpos($sentence, 'update_r1 update_r1')) continue;
  if (FALSE !== strpos($sentence, 'update_r2 update_r2')) continue;
  if (FALSE !== strpos($sentence, 'update_r3 update_r3')) continue;
  if (FALSE !== strpos($sentence, 'sync_r1 sync_r1')) continue;
  if (FALSE !== strpos($sentence, 'sync_r2 sync_r2')) continue;
  if (FALSE !== strpos($sentence, 'sync_r3 sync_r3')) continue;
  // if (FALSE !== strpos($sentence, 'sync_r1 sync_r2 sync_r1')) continue;
  // if (FALSE !== strpos($sentence, 'sync_r2 sync_r1 sync_r2')) continue;
  // if (FALSE !== strpos($sentence, 'sync_r2 sync_r3 sync_r2')) continue;
  // if (FALSE !== strpos($sentence, 'sync_r3 sync_r1 sync_r3')) continue;
  // if (FALSE !== strpos($sentence, 'sync_r3 sync_r2 sync_r3')) continue;

  // only want cases where every update has at least a chance to propagate everywhere
  $lastUpdate = strrpos($sentence, 'update_');
  if (FALSE !== strpos($sentence, '_r1') && FALSE === strpos($sentence, 'sync_r1', $lastUpdate)) continue;
  if (FALSE !== strpos($sentence, '_r2') && FALSE === strpos($sentence, 'sync_r2', $lastUpdate)) continue;
  if (FALSE !== strpos($sentence, '_r3') && FALSE === strpos($sentence, 'sync_r3', $lastUpdate)) continue;

  // determine if an equivalent item is already in the list
  $equivalents = array(
    yassgen_substitute($sentence, array('r1', 'r3', 'r2')),
    yassgen_substitute($sentence, array('r2', 'r1', 'r3')),
    yassgen_substitute($sentence, array('r2', 'r3', 'r1')),
    yassgen_substitute($sentence, array('r3', 'r1', 'r2')),
    yassgen_substitute($sentence, array('r3', 'r2', 'r1')),
  );
  $intersect = array_intersect($keepers, $equivalents);
  if (!empty($intersect)) continue;
  
  // if this is the prefix of an existing sentence, then don't bother with it
  foreach ($keepers as $keeper) {
    if (0 === strpos($keeper, $sentence)) continue 2;
  }

  // ok, seems interesting  
  $keepers[] = $sentence;
}

printf("pruned %d to %d\n", count($sentences), count($keepers));
print_r($keepers);

function yassgen_revlen($a,$b) {
  if (strlen($a) < strlen($b)) return 1;
  if (strlen($a) > strlen($b)) return -1;
  return strcmp($a,$b);
}

/**
 * Generate combinations over an alphabet
 *
 * @param $words array of words
 * @param $delim a string to use in joining into a combintation
 * @param $maxlen maximum number of words to include a combination 
 */
function yassgen_combinations($words, $delim, $maxlen, $prefix = '') {
  if ($maxlen == 0) return array($prefix);
  
  $result = array($prefix);
  foreach ($words as $word) {
    $recurse = yassgen_combinations($words, $delim, $maxlen-1, $prefix . $delim . $word);
    $result = array_unique(array_merge($recurse, $result));
  }
  return $result;
}

function yassgen_substitute($sentence, $translation) {
  $toTmp = array('r1' => 'tmp1', 'r2' => 'tmp2', 'r3' => 'tmp3');
  $fromTmp = array('tmp1' => $translation[0], 'tmp2' => $translation[1], 'tmp3' => $translation[2]);
  return strtr(strtr($sentence, $toTmp), $fromTmp);
}

// an update_X is invalid if it's not preceded by sync_X
function yassgen_syncbeforeedit($sentence) {
  for ($i = 2; $i < 10; $i++) {
    $syncPos = strpos($sentence, "sync_r${i}");
    if ($syncPos === FALSE) continue;
    
    $updatePos = strpos($sentence, "update_r${i}");
    if ($updatePos < $syncPos) return FALSE;
  }
  return TRUE;
}

function yassgen_isCli() {
  return (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']));
}
