<?php
/*
 * Looking Glass - An easy to deploy Looking Glass
 * Copyright (C) 2014-2023 Guillaume Mazoyer <guillaume@mazoyer.eu>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301  USA
 */

require_once('includes/config.defaults.php');
require_once('includes/captcha.php');
require_once('includes/utils.php');
require_once('config.php');

final class LookingGlass {
  private $release;
  private $frontpage;
  private $contact;
  private $misc;
  private $captcha;
  private $routers;
  private $vrfs;
  private $doc;

  public function __construct($config) {
    set_defaults_for_routers($config);

    $this->release = $config['release'];
    $this->frontpage = $config['frontpage'];
    $this->contact = $config['contact'];
    $this->misc = $config['misc'];
    $this->captcha = new Captcha($config['captcha']);
    $this->routers = $config['routers'];
    $this->doc = $config['doc'];
    $this->vrfs = $config['vrfs'];
  }

  private function router_count() {
    if ($this->frontpage['router_count'] > 0)
      return $this->frontpage['router_count'];
    else
      return count($this->routers);
  }

  private function command_count() {
    if ($this->frontpage['command_count'] > 0)
      return $this->frontpage['command_count'];
    else
      $command_count = 0;
      foreach (array_keys($this->doc) as $cmd) {
        if (isset($this->doc[$cmd]['command'])) {
          $command_count++;
        }
      }
      return $command_count;
  }

  private function render_routers() {
    print('<div class="mb-3">');
    print('<label class="form-label" for="routers">Router to use</label>');
    print('<select size="'.$this->router_count().'" class="form-select" name="routers" id="routers">');

    $first = true;
    foreach (array_keys($this->routers) as $router) {
      // IPv6 and IPv4 both disabled for the router, ignore it
      if ($this->routers[$router]['disable_ipv6'] &&
          $this->routers[$router]['disable_ipv4']) {
        continue;
      }

      print('<option value="'.$router.'"');
      if ($first) {
        $first = false;
        print(' selected="selected"');
      }
      print('>'.$this->routers[$router]['desc']);
      print('</option>');
    }

    print('</select>');
    print('</div>');
  }

  private function render_commands() {
    print('<div class="mb-3">');
    print('<label class="form-label" for="query">Command to issue</label>');
    print('<select size="'.$this->command_count().'" class="form-select" name="query" id="query">');
    $selected = ' selected="selected"';
    foreach (array_keys($this->doc) as $cmd) {
      if (isset($this->doc[$cmd]['command'])) {
        print('<option value="'.$cmd.'"'.$selected.'>'.$this->doc[$cmd]['command'].'</option>');
      }
      $selected = '';
    }
    print('</select>');
    print('</div>');
  }

  private function render_parameter() {
    if ($this->frontpage['show_visitor_ip']) {
      $requester = htmlentities(get_requester_ip());
    } else {
      $requester = "";
    }
    print('<div class="mb-3">');
    print('<label class="form-label for="input-param">Parameter</label>');
    print('<div class="input-group mb-3">');
    print('<input class="form-control" name="parameter" id="input-param" autofocus value="'.$requester.'" />');
    print('<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#help">');
    print('<i class="bi bi-question-circle-fill"></i> Help');
    print('</button>');
    print('</div>');
    print('</div>');
  }

  private function render_vrfs() {
    if (!$this->vrfs['enabled']) {
        return;
    }
    print('<div class="mb-3">');
    print('<label class="form-label" for="vrf">VRF</label>');
    print('<select size="5" class="form-select" name="vrf" id="vrf">');
    print('<option value="none" selected>None/Disable</option>');
    foreach (array_values($this->vrfs['vrfs']) as $vrf) {
      print('<option value="'.$vrf.'">'.$vrf.'</option>');
    }
    print('</select>');
    print('</div>');
  }

  private function render_buttons() {
    $this->captcha->render();
    print('<div class="row">');
    print('<div class="col-4 offset-4 btn-group btn-group-lg">');
    print('<button class="btn btn-primary" id="send" type="submit">Enter</button>');
    print('<button class="btn btn-danger" id="clear" type="reset">Reset</button>');
    print('</div>');
    print('</div>');
  }

  private function render_header() {
    if ($this->frontpage['header_link']) {
      print('<a href="'.$this->frontpage['header_link'].'" class="text-decoration-none" title="Home">');
    }
    print('<div class="header_bar text-center mx-auto">');
    if ($this->frontpage['show_title']) {
      print('<h1>'.htmlentities($this->frontpage['title']).'</h1><br>');
    }
    if ($this->frontpage['image']) {
      print('<img src="'.$this->frontpage['image'].'" alt="Logo"');
      if ((int) $this->frontpage['image_width'] > 0) {
        print(' width="'.$this->frontpage['image_width'].'"');
      }
      if ((int) $this->frontpage['image_height'] > 0) {
        print(' height="'.$this->frontpage['image_height'].'"');
      }
      print('/>');
    }
    print('</div>');
    if ($this->frontpage['header_link']) {
      print('</a>');
    }
  }

  private function render_content() {
    print('<div class="alert alert-danger alert-dismissible" id="error">');
    print('<strong>Error!</strong>&nbsp;<span id="error-text"></span>');
    print('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
    print('</div>');
    print('<div class="content text-center" id="command_options">');
    print('<form role="form" action="execute.php" method="post">');
    print('<fieldset id="command_properties">');

    foreach ($this->frontpage['order'] as $element) {
      switch ($element) {
        case 'routers':
          $this->render_routers();
          break;

        case 'commands':
          $this->render_commands();
          break;

        case 'parameter':
          $this->render_parameter();
          break;

        case 'buttons':
          $this->render_buttons();
          break;

        case 'vrfs':
          $this->render_vrfs();
          break;

        default:
          break;
      }
    }

    print('<input type="text" class="d-none" name="dontlook" placeholder="Don\'t look at me!" />');
    print('<fieldset>');
    print('</form>');
    print('</div>');
    print('<div class="loading">');
    print('<div class="progress">');
    print('<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">');
    print('</div>');
    print('</div>');
    print('</div>');
    print('<div class="result">');
    print('<div id="output"></div>');
    print('<div class="row">');
    print('<div class="col-4 offset-4 btn-group btn-group-lg">');
    print('<button class="btn btn-danger" id="backhome">Reset</button>');
    print('</div>');
    print('</div>');
    print('</div>');
  }

  private function render_footer() {
    print('<div class="footer_bar text-center">');
    print('<p class="text-center">');

    if ($this->frontpage['show_visitor_ip']) {
      printf('Your IP address: %s<br>', htmlentities(get_requester_ip()));
    }

    if ($this->frontpage['disclaimer']) {
      print($this->frontpage['disclaimer']);
      print('<br><br>');
    }

    if ($this->frontpage['peering_policy_file']) {
      print('<button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#peering-policy"><i class="bi bi-list-task"></i> Peering Policy</button>');
      print('<br><br>');
    }

    if ($this->contact['name'] && $this->contact['mail']) {
      print('Contact:&nbsp;');
      print('<a href="mailto:'.$this->contact['mail'].'" class="text-decoration-none">'.
        htmlentities($this->contact['name']).'</a>');
    }

    print('<br><br>');
    print('<span class="origin">Powered by <a href="'.$this->release['repository'].'" class="text-decoration-none" title="Looking Glass Project">Looking Glass '.$this->release['version'].'</a></span>');
    print('</p>');
    print('</div>');
  }

  private function render_peering_policy_modal() {
    print('<div id="peering-policy" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">');
    print('<div class="modal-dialog modal-dialog-centered modal-lg">');
    print('<div class="modal-content">');
    print('<div class="modal-header">');
    print('<h5 class="modal-title">Peering Policy</h5>');
    print('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
    print('</div>');
    print('<div class="modal-body">');
    if (!file_exists($this->frontpage['peering_policy_file'])) {
      print('The peering policy file ('.
        $this->frontpage['peering_policy_file'].') does not exist.');
    } else if (!is_readable($this->frontpage['peering_policy_file'])) {
      print('The peering policy file ('.
        $this->frontpage['peering_policy_file'].') is not readable.');
    } else {
      include($this->frontpage['peering_policy_file']);
    }
    print('</div>');
    print('<div class="modal-footer">');
    print('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>');
    print('</div>');
    print('</div>');
    print('</div>');
    print('</div>');
  }

  private function render_help_modal() {
    print('<div id="help" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">');
    print('<div class="modal-dialog modal-dialog-centered modal-lg">');
    print('<div class="modal-content">');
    print('<div class="modal-header">');
    print('<h5 class="modal-title">Help</h5>');
    print('<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>');
    print('</div>');
    print('<div class="modal-body">');
    print('<h4>Command <span class="badge badge-dark"><small id="command-reminder"></small></span></h4>');
    print('<p id="description-help"></p>');
    print('<h4>Parameter</h4>');
    print('<p id="parameter-help"></p>');
    print('</div>');
    print('<div class="modal-footer">');
    print('<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>');
    print('</div>');
    print('</div>');
    print('</div>');
    print('</div>');
  }

  public function render() {
    print('<!DOCTYPE html>');
    print('<html lang="en">');
    print('<head>');
    print('<meta charset="utf-8">');
    print('<meta name="viewport" content="width=device-width, initial-scale=1">');
    print('<meta name="keywords" content="Looking Glass, LG, BGP, prefix-list, AS-path, ASN, traceroute, ping, IPv4, IPv6, Cisco, Juniper, Internet">');
    print('<meta name="description" content="'.$this->frontpage['title'].'">');
    if ($this->frontpage['additional_html_header']) {
      print($this->frontpage['additional_html_header']);
    }
    print('<title>'.htmlentities($this->frontpage['title']).'</title>');
    print('<link href="libs/bootstrap-5.3.1/css/bootstrap.min.css" rel="stylesheet">');
    print('<link href="libs/bootstrap-icons-1.10.5/bootstrap-icons.css" rel="stylesheet">');
    print('<link href="'.$this->frontpage['css'].'" rel="stylesheet">');
    print('</head>');
    print('<body class="d-flex flex-column h-100">');
    print('<main class="flex-shrink-0">');
    print('<div class="container">');
    $this->render_header();
    $this->render_content();
    $this->render_footer();
    $this->render_help_modal();
    if ($this->frontpage['peering_policy_file']) {
      $this->render_peering_policy_modal();
    }
    print('</div>');
    print('</main>');
    print('</body>');
    print('<script src="libs/jquery-3.7.0.min.js"></script>');
    print('<script src="libs/bootstrap-5.3.1/js/bootstrap.min.js"></script>');
    print('<script src="js/looking-glass.js"></script>');
    $this->captcha->render_script();
    print('</html>');
  }
}

$looking_glass = new LookingGlass($config);
$looking_glass->render();

// End of index.php
