/*
  Facebook Module for webtrees

  Copyright (C) 2012 Matthew N.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

$(document).ready(function init_facebook() {

  $("#login-form, #register-form").before(
    '<div id="facebook-login-box" style="display: inline-block">' +
    '<form id="login-form" action="module.php" method="get">' +
    '<input type="hidden" name="mod" value="facebook"/>' +
    '<input type="hidden" name="mod_action" value="connect"/>' +
    '<button id="facebook-login-button">Login with Facebook</button>' +
    '</form>' +
    '</div>');

});
