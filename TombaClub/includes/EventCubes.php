<?php

namespace TombaClub;

class EventCubes {
  /**
   * Generates a polygon that goes behind the main front face polygon.
   */
  private static function makeBackPolygon($front, $back) {
    $tlFront = $front[0];
    $tlBack = $back[0];
    $left = $tlFront[0] < $tlBack[0] ? $front : $back;
    $right = $tlFront[0] > $tlBack[0] ? $front : $back;
    $top = $tlFront[1] < $tlBack[1] ? $front : $back;
    $bottom = $tlFront[1] > $tlBack[1] ? $front : $back;
    
    $coordinates = [];
    $coordinates[] = $left[0];
    $coordinates[] = $top[0];
    $coordinates[] = $top[1];
    $coordinates[] = $right[1];
    $coordinates[] = $right[2];
    $coordinates[] = $bottom[2];
    $coordinates[] = $bottom[3];
    $coordinates[] = $left[3];

    return $coordinates;
  }

  /**
   * Offsets a list of polygon coordinates by given x/y values.
   */
  private static function offsetPolygon($coordinateSets, $offsetX, $offsetY) {
    $offset = array_map(
      function($poly) use ($offsetX, $offsetY) {
        return [$poly[0] + $offsetX, $poly[1] + $offsetY];
      },
      $coordinateSets
    );
    return $offset;
  }

  /**
   * Generates a set of polygons for an event cube.
   */
  private static function makeCubePolygons($offsetX, $offsetY, $options) {
    $steps = $options['steps'];
    $width = $options['width'];
    $height = $options['height'];

    $maxOffsetX = $options['maxOffsetX'];
    $maxOffsetY = $options['maxOffsetY'];

    // The base distance: this is how far the front face is from the left/top sides.
    $baseDistanceX = ($width / $maxOffsetX);
    $baseDistanceY = ($height / $maxOffsetY);

    // Face size of the squares.
    $faceWidth = $width - ($baseDistanceX * 2);
    $faceHeight = $height - ($baseDistanceY * 2);

    // Offset values of the back face.
    $offsetValueX = ($baseDistanceX) * $offsetX;
    $offsetValueY = ($baseDistanceY) * $offsetY;

    $base = [
      [0, 0],
      [$faceWidth, 0],
      [$faceWidth, $faceHeight],
      [0, $faceHeight],
    ];

    $faceBackItems = [];
    for ($n = 0; $n < $steps; ++$n) {
      $value = ($n + 1) / ($steps);
      $faceBackItems[] = self::offsetPolygon($base, $offsetValueX * $value, $offsetValueY * $value);
    }
    $faceFront = self::offsetPolygon($base, $baseDistanceX, $baseDistanceY);
    $polygons = [];
    $polygons[] = [$faceFront, 'var(--cube-face-0)'];
    for ($n = 0; $n < $steps; ++$n) {
      $item = $faceBackItems[$n];
      $face = $n + 1;
      $polygons[] = [self::makeBackPolygon($faceFront, self::offsetPolygon($item, $baseDistanceX, $baseDistanceY)), "var(--cube-face-{$face})"];
    }
    $polygons = array_reverse($polygons);

    return $polygons;
  }

  /**
   * Generates a single event cube for a letter.
   */
  private static function generateCube($letter, $offsetX = 1, $offsetY = 1, $options = []) {
    $options = [
      ...[
        'isDescender' => false,
        'steps' => 5,
        'width' => 512,
        'height' => 512,
        'maxOffsetX' => 6,
        'maxOffsetY' => 8,
      ],
      ...$options,
    ];

    $offsetX = max(-1, min(1, $offsetX));
    $offsetY = max(-1, min(1, $offsetY));
    $polygons = self::makeCubePolygons($offsetX, $offsetY, $options);

    $stroke = '40';
    $size = '350';
    $y = '58%';
    if ($options['isDescender']) {
      $y = '43%';
    }
    if ($options['isHuge']) {
      $size = '330';
      $y = '49%';
    }

    $buffer = [];
    $buffer[] = '<svg class="tc-cube" viewBox="0 0 512 512" role="img" aria-labelledby="letter" width="512" height="512" preserveAspectRatio="xMidYMid meet">';
    $buffer[] = '<title id="title">'.$letter.'</title>';
    $buffer[] = '<g fill="none" stroke-linejoin="round" stroke-linecap="round">';
    foreach ($polygons as $polygon) {
      $points = array_map(
        function($poly) {
          $x = number_format($poly[0], 4, '.', '');
          $y = number_format($poly[1], 4, '.', '');
          return "{$x},{$y}";
        },
        $polygon[0]
      );
      $buffer[] = '<polygon points="'.implode(' ', $points).'" fill="'.$polygon[1].'" />';
    }
    $buffer[] = '<text x="50%" y="'.$y.'" font-family="Comic Sans" font-size="'.$size.'" fill="white" stroke="black" stroke-width="'.$stroke.'" text-anchor="middle" dominant-baseline="middle" paint-order="stroke fill">'.$letter.'</text>';
    $buffer[] = '</g>';
    $buffer[] = '</svg>';

    return implode("", $buffer);
  }

  /**
   * Finds the minimum limit required to fit the longest single word.
   */
  private static function getMinimumLimit($phrase, $limit = 20) {
    $words = preg_split('/\s+/', $phrase);
    foreach ($words as $word) {
      $len = strlen($word);
      if ($limit < $len) {
        $limit = $len;
      }
    }
    return $limit;
  }

  /**
   * Splits an event name into lines.
   * 
   * This predetermines how long lines should be.
   */
  private static function splitIntoLines($phrase, $limit) {
    $words = preg_split('/\s+/', $phrase);
    $lines = [];
    while (count($words) > 0) {
      $lineWords = [];
      $lineLength = 0;
      while (count($words) > 0) {
        $word = array_shift($words);
        $testLength = $lineLength + strlen($word);
        if ($testLength > $limit) {
          array_unshift($words, $word);
          break;
        }
        $lineWords[] = $word;
        $lineLength = $testLength + 1;
      }
      $total = 0;
      foreach ($lineWords as $w) {
        $total += strlen($w);
      }
      $lines[] = ['words' => $lineWords, 'total' => $total];
    }
    return $lines;
  }

  /**
   * Merges the defaults in with the passed options.
   */
  private static function mergeDefaultOptions($options = []) {
    return [
      ...[
        'limit' => 15,
        'top' => 0.75,
        'bottom' => 0.4,
      ],
      ...$options,
    ];
  }

  /**
   * Generates a set of event cubes for an event name.
   */
  public static function generateEventCubes($eventName, $options = []) {
    $options = self::mergeDefaultOptions($options);
    $limit = self::getMinimumLimit($eventName, $options['limit']);
    $lines = self::splitIntoLines($eventName, $limit);
    $buffer = [];

    for ($n = 0; $n < count($lines); ++$n) {
      $line = $lines[$n];
      $count = 0;
      $total = $line['total'];
      
      $vertical = $options['top'] - (($options['top'] - $options['bottom']) * ($n / (max(1, count($lines) - 1))));
      $buffer[] = '<span class="l">';
      foreach ($line['words'] as $word) {
        $buffer[] = '<span class="w">';
        $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($letters as $letter) {
          $delay = $count / 10;
          $progress = ((($total - 1) - ($count * 2)) / ($total - 1));
          $index = abs(abs((($total - 1) / 2) - $count) - (($total - 1) / 2));
          $props = [
            'isDescender' => in_array($letter, ['g', 'j', 'p', 'q', 'y']),
            'isHuge' => in_array($letter, ['j']),
          ];
          $cube = self::generateCube($letter, $progress, $vertical, $props);
          
          $buffer[] = '<span data-n="'.$count.'" style="z-index: '.$index.'; --delay: '.number_format($delay, 1, '.', '').'s;">'.$cube.'</span>';
          
          $count += 1;
        }
        $buffer[] = '</span>';
      }
      $buffer[] = '</span>';
    }

    return '<div class="c">'.implode("", $buffer).'</div>';
  }
}
