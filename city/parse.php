<?php
/*
from https://stackoverflow.com/questions/9186038/php-generate-rgb
*/
function getColor($text) {
    $hash = md5('color' . $text); // modify 'color' to get a different palette
    return array(
        hexdec(substr($hash, 0, 2)), // r
        hexdec(substr($hash, 2, 2)), // g
        hexdec(substr($hash, 4, 2))); //b
}

$targets = array(
  'pm25' => '細懸浮微粒 PM 2.5  (μg/m 3 )',
  'pm10' => '懸浮微粒 PM 10  (μg/m 3 )',
  'so2' => '二氧化硫 SO2 (ppb)',
  'o3' => '臭氧 O3 (ppb)',
  'nox' => '氮氧化物 NOx (ppb)',
);

$result = $labels = array();
$labelDone = false;

foreach($targets AS $key => $val) {
  $result[$key] = array();
  //2005Q1 ~ 2018Q1
  for($i = 2005; $i <= 2018; $i++) {
    $result[$key][] = array(
      'title' => $i . 'Q1',
      'begin' => strtotime($i . '-01-01'),
      'end' => strtotime($i . '-03-31'),
      'count' => array(),
      'sum' => array(),
      'avg' => array(),
    );
    if(false === $labelDone) {
      $labels[] = $i . 'Q1';
    }
    if($i !== 2018) {
      $result[$key][] = array(
        'title' => $i . 'Q2',
        'begin' => strtotime($i . '-04-01'),
        'end' => strtotime($i . '-06-30'),
        'count' => array(),
        'sum' => array(),
        'avg' => array(),
      );
      $result[$key][] = array(
        'title' => $i . 'Q3',
        'begin' => strtotime($i . '-07-01'),
        'end' => strtotime($i . '-09-30'),
        'count' => array(),
        'sum' => array(),
        'avg' => array(),
      );
      $result[$key][] = array(
        'title' => $i . 'Q4',
        'begin' => strtotime($i . '-10-01'),
        'end' => strtotime($i . '-12-31'),
        'count' => array(),
        'sum' => array(),
        'avg' => array(),
      );
      if(false === $labelDone) {
        $labels[] = $i . 'Q2';
        $labels[] = $i . 'Q3';
        $labels[] = $i . 'Q4';
      }
    }
  }
  $labelDone = true;
}

$headers = array();
foreach(glob(dirname(__DIR__) . '/raw/air_daily/*.csv') AS $csvFile) {
  $csvContent = file_get_contents($csvFile);
  $pos = strpos($csvContent, "\n1,");
  $headerText = substr($csvContent, 0, $pos);
  $headerText = str_replace(array("\n", "\r"), array(' ', ''), $headerText);
  $header = str_getcsv($headerText);
  foreach($header AS $k => $v) {
    $headers[$v] = true;
  }
  $headerCount = count($header);
  $fh = fopen($csvFile, 'r');
  fseek($fh, $pos);
  while($line = fgetcsv($fh, 2048)) {
    if(count($line) !== $headerCount) {
      continue;
    }
    $data = array_combine($header, $line);
    $time = strtotime($data['監測日期']);
    foreach($result AS $targetKey => $targetValue) {
      foreach($targetValue AS $k => $dataset) {
        if($time >= $dataset['begin'] && $time <= $dataset['end']) {
          if(!isset($result[$targetKey][$k]['count'][$data['縣市']])) {
            $result[$targetKey][$k]['count'][$data['縣市']] = 0;
            $result[$targetKey][$k]['sum'][$data['縣市']] = 0;
          }
          ++$result[$targetKey][$k]['count'][$data['縣市']];
          $result[$targetKey][$k]['sum'][$data['縣市']] += $data[$targets[$targetKey]];
        }
      }
    }
  }
}

$datasets = array();
foreach($result AS $targetKey => $targetValue) {
  if(!isset($datasets[$targetKey])) {
    $datasets[$targetKey] = array();
  }
  foreach($targetValue AS $k => $dataset) {
    foreach($dataset['count'] AS $city => $val) {
      if(!isset($datasets[$targetKey][$city])) {
        $color = 'rgb(' . implode(',', getColor($city)) . ')';
        $datasets[$targetKey][$city] = array(
          'label' => $city,
          'backgroundColor' => $color,
          'borderColor' => $color,
          'fill' => false,
          'hidden' => true,
          'data' => array(),
        );
      }
      $datasets[$targetKey][$city]['data'][] = round($result[$targetKey][$k]['sum'][$city] / $result[$targetKey][$k]['count'][$city], 2);
    }
  }
}

foreach($datasets AS $target => $data) {
  file_put_contents(__DIR__ . '/' . $target . '.json', json_encode(array(
    'labels' => $labels,
    'datasets' => array_values($data),
  )));
}
