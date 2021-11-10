<?php
namespace MediaWiki\Extension\Radar;

class Hooks {
   public static function onParserFirstCallInit( \Parser $parser ) {
      $parser->setHook( 'radar', [ self::class, 'render' ] );
   }

   // Render <radar>
   public static function render( $input, array $args, \Parser $parser, \PPFrame $frame ) {
      $size = null;
      if (isset($args["size"]) && is_numeric($args["size"])) {
         $size = round($args["size"]);
         if ($size <= 150) {
            $size = 150;
         }
      }

      $max = 1;
      if (isset($args["max"]) && is_numeric($args["max"])) {
         $max = round($args["max"]);
         if ($max == 0) {
            $max = 1;
         }
      }

      $points = [];
      $trim = preg_replace('/^\s+/', '', $input);
      foreach (preg_split('/\n+/', $trim) as $line) {
         $pair = preg_split('/\s+/', $line, 2);
         if (is_numeric($pair[0])) {
            $points[] = array(
               "len" => $pair[0] / $max,
               "label" => $pair[1] ?? ""
            );
         }
      }

      $parser->getOutput()->addModuleStyles( "ext.radar" );
      return Hooks::generate($points, $size);
   }

   private static function generate( array $points, $size=null ) {
      $n = sizeof($points);
      $width = $size ?? 300;
      $height = $width;

      $doc = new \DOMDocument();

      $root = $doc->createElement("svg");
      $root->setAttribute("viewBox", "0 0 $width $height");
      $root->setAttribute("width", $width);
      $root->setAttribute("height", $height);
      $root->setAttribute("xmlns", "http://www.w3.org/2000/svg");
      $root->setAttribute("class", "radar");
      $doc->appendChild($root);

      $circlePoint = function($angle, $length, $minOffset=null, $maxOffset=null)
                         use ($width) {
         $minOffset = $minOffset ?? 10;
         $maxOffset = $maxOffset ?? 24;
         $center = $width / 2;
         $scale = $center - $maxOffset - $minOffset;
         return [
            $center - sin(-$angle) * ($minOffset + $scale * $length),
            $center - cos(-$angle) * ($minOffset + $scale * $length),
         ];
      };

      $turn = 2 * pi() / $n;
      $indexPoint = function($index, $length, $minOffset=null, $maxOffset=null)
                        use ($turn, $circlePoint) {
         return $circlePoint($index * $turn, $length, $minOffset, $maxOffset);
      };

      $xy = function( array $pair ) {
         return join(',', $pair);
      };

      /*
       * Grid lines
       */
      $gridBase = $doc->createElement('path');
      $gridBase->setAttribute("class", "radar-grid");

      // Bounding circle
      $circle = $doc->createElement('circle');
      $circle->setAttribute("class", "radar-circle");
      $circle->setAttribute("r", $width / 2 - 22);
      $circle->setAttribute("cx", $width / 2);
      $circle->setAttribute("cy", $width / 2);
      $root->appendChild($circle);

      // Radial axis
      $steps = 5;
      if ($width <= 250) { $steps = 4; }
      if ($width <= 200) { $steps = 3; }

      for ($l = 0; $l <= $steps; ++$l) {
         $d = "M " . $xy($indexPoint(0, $l/$steps));
         for ($i = 1; $i <= $n; ++$i) {
            $d .= " L " . $xy($indexPoint($i, $l/$steps));
         }
         $node = $gridBase->cloneNode();
         $node->setAttribute("d", $d);
         $root->appendChild($node);
      }

      // Central axis
      for ($i = 0; $i < $n; ++$i) {
         $node = $gridBase->cloneNode();
         $node->setAttribute("d",
            "M " . $xy($indexPoint($i, 0)) . " L " . $xy($indexPoint($i, 1))
         );
         $root->appendChild($node);
      }

      /*
       * Labels
       */

      // If there are multiple SVG's on the page, the <textPath>s will all go
      // with for the first one's defs for some reason. So we need to make
      // sure the ids are distinct globally.
      static $id = 0;
      $id += 1;

      $defs = $doc->createElement('defs');
      for ($i = 0; $i < $n; ++$i) {
         $maxOffset = 10;
         $r = $width / 2 - $maxOffset;
         $angle = $turn * $i;
         $sweepbit = 1;
         if ($angle > pi()/2 && $angle < 3*pi()/2) {
            $sweepbit = 0;
            $angle -= pi();
         }
         $node = $doc->createElement('path');
         $node->setAttribute("id", "label-$id-$i");
         $node->setAttribute("d",
            "M " . $xy($circlePoint($angle - pi()/2, 1, null, $maxOffset))
            . " A " . join(",", [ $r, $r, 0, 0, $sweepbit,
               $xy($circlePoint($angle + pi()/2, 1, null, $maxOffset)) ])
         );
         $defs->appendChild($node);
      }
      $root->appendChild($defs);

      $textBase = $doc->createElement("text");
      $textBase->setAttribute("class", "radar-label");
      $textPathBase = $doc->createElement("textPath");
      $textPathBase->setAttribute("startOffset", "50%");

      for ($i = 0; $i < $n; ++$i) {
         $node = $textBase->cloneNode();
         $child = $textPathBase->cloneNode();
         $child->setAttribute("href", "#label-$id-$i");
         $child->textContent = $points[$i]["label"];
         $node->appendChild($child);
         $root->appendChild($node);
      }

      /*
       * Plot
       */
      $d = "";
      for ($i = 0; $i < $n; ++$i) {
         $d .= $xy($indexPoint($i, $points[$i]["len"])) . " ";
      }
      $plot = $doc->createElement("polygon");
      $plot->setAttribute("class", "radar-plot");
      $plot->setAttribute("points", $d);
      $root->appendChild($plot);

      return $doc->saveHTML();
   }
}
