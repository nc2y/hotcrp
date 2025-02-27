<?php
// src/partials/p_resetpassword.php -- HotCRP password reset partials
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

class ResetPassword_Partial {
    private $_reset_cap;
    private $_reset_capdata;
    private $_reset_user;

    // Password reset
    function reset_request(Contact $user, Qrequest $qreq) {
        global $Now;
        ensure_session();
        $conf = $user->conf;
        if ($qreq->resetcap === null
            && preg_match('{\A/(U?1[-\w]+)(?:/|\z)}', Navigation::path(), $m)) {
            $qreq->resetcap = $m[1];
        }

        $resetcap = trim((string) $qreq->resetcap);
        if ($resetcap === "") {
            // nothing
        } else if (strpos($resetcap, "@") !== false) {
            if ($qreq->go
                && $qreq->post_ok()) {
                $nqreq = new Qrequest("POST", ["email" => $resetcap]);
                $nqreq->approve_post();
                $url = LoginHelper::login($conf, $nqreq, "forgot");
                if ($url !== false) {
                    $conf->self_redirect();
                }
                if (Ht::problem_status_at("email")) {
                    Ht::error_at("resetcap");
                }
            }
        } else {
            if (preg_match('{\A/?(U?1[-\w]+)/?\z}', $resetcap, $m)) {
                $this->_reset_cap = $m[1];
            }
            if ($this->_reset_cap) {
                $capmgr = $conf->capability_manager($this->_reset_cap);
                $this->_reset_capdata = $capmgr->check($this->_reset_cap);
            }
            if (!$this->_reset_capdata
                || $this->_reset_capdata->capabilityType != CAPTYPE_RESETPASSWORD) {
                Ht::error_at("resetcap", "Unknown or expired password reset code. Please check that you entered the code correctly.");
                $this->_reset_capdata = null;
            }
        }

        if ($this->_reset_capdata) {
            if (str_starts_with($this->_reset_cap, "U")) {
                $this->_reset_user = $conf->contactdb_user_by_id($this->_reset_capdata->contactId);
            } else {
                $this->_reset_user = $conf->user_by_id($this->_reset_capdata->contactId);
            }
        }

        if ($this->_reset_user
            && $qreq->go
            && $qreq->post_ok()) {
            $p1 = trim((string) $qreq->password);
            $p2 = trim((string) $qreq->password2);
            if ($p1 === "") {
                if ($p2 !== "" || $qreq->autopassword) {
                    Ht::error_at("password", "You must enter a password.");
                }
            } else if ($p1 !== $p2) {
                Ht::error_at("password", "The passwords you entered did not match.");
                Ht::error_at("password2");
            } else if (!Contact::valid_password($p1)) {
                Ht::error_at("password", "Invalid password.");
                Ht::error_at("password2");
            } else {
                $accthere = $conf->user_by_email($this->_reset_user->email)
                    ? : Contact::create($conf, null, $this->_reset_user);
                $accthere->change_password($p1, 0);
                $accthere->log_activity("Password reset via " . substr($this->_reset_cap, 0, 8) . "...");
                $conf->confirmMsg("Your password has been changed. You may now sign in to the conference site.");
                $capmgr->delete($this->_reset_capdata);
                $user->save_session("password_reset", (object) [
                    "time" => $Now, "email" => $this->_reset_user->email, "password" => $p1
                ]);
                Navigation::redirect($conf->hoturl("index", ["signin" => 1]));
            }
        } else if (!$this->_reset_user
                   && $this->_reset_capdata) {
            Ht::error_at("resetcap", "This password reset code refers to a user who no longer exists. Either create a new account or contact the conference administrator.");
        } else if ($qreq->cancel) {
            Navigation::redirect($conf->hoturl("index"));
        }
    }
    function render_reset_head(Contact $user, Qrequest $qreq, $gx) {
        $user->conf->header("Reset password", "resetpassword", ["action_bar" => false]);
        $gx->push_render_cleanup("footer");
        if ($user->conf->external_login()) {
            $user->conf->errorMsg("Password reset links aren’t used for this conference. Contact your system administrator if you’ve forgotten your password.");
            return false;
        }
    }
    private function _render_reset_success($user, $qreq) {
        if (!isset($qreq->autopassword)
            || trim($qreq->autopassword) !== $qreq->autopassword
            || strlen($qreq->autopassword) < 16
            || !preg_match("/\\A[-0-9A-Za-z@_+=]*\\z/", $qreq->autopassword)) {
            $qreq->autopassword = Contact::random_password();
        }
        echo Ht::hidden("resetcap", $this->_reset_cap),
            Ht::hidden("autopassword", $qreq->autopassword),
            '<p class="mb-5">Use this form to reset your password. You may want to use the random password we’ve chosen.</p>',
            '<div class="f-i"><label>Email</label>', htmlspecialchars($this->_reset_user->email), '</div>',
            Ht::entry("email", $this->_reset_user->email, ["class" => "hidden", "autocomplete" => "username"]),
            '<div class="f-i"><label>Suggested password</label>',
            htmlspecialchars($qreq->autopassword), '</div>',

            '<div class="', Ht::control_class("password", "f-i"), '">',
            '<label for="password">New password</label>',
            Ht::password("password", "", ["class" => "want-focus fullw", "tabindex" => 1, "size" => 36, "id" => "password", "autocomplete" => "new-password"]),
            Ht::render_messages_at("password"),
            '</div>',

            '<div class="', Ht::control_class("password2", "f-i"), '">',
            '<label for="password2">New password (again)</label>',
            Ht::password("password2", "", ["class" => "fullw", "tabindex" => 1, "size" => 36, "id" => "password2", "autocomplete" => "new-password"]),
            Ht::render_messages_at("password2"),
            '</div>';
    }
    private function _render_reset_other($user, $qreq) {
        echo '<p class="mb-5">', Home_Partial::forgot_message($user->conf),
            ' Or enter a password reset code if you have one.</p>',
            '<div class="', Ht::control_class("resetcap", "f-i"), '">',
            '<label for="resetcap">Email or password reset code</label>',
            Ht::entry("resetcap", $qreq->resetcap, ["class" => "want-focus fullw", "tabindex" => 1, "size" => 36, "id" => "resetcap"]),
            Ht::render_messages_at("resetcap"),
            Ht::render_messages_at("email"),
            '</div>';
    }
    function render_reset_body(Contact $user, Qrequest $qreq, $gx) {
        echo '<div class="homegrp" id="homereset">',
            Ht::form($user->conf->hoturl("resetpassword"), ["class" => "compact-form"]),
            Ht::hidden("post", post_value());
        if ($this->_reset_user) {
            $this->_render_reset_success($user, $qreq);
            $k = "btn-success";
        } else {
            $this->_render_reset_other($user, $qreq);
            $k = "btn-primary";
        }
        echo '<div class="popup-actions">',
            Ht::submit("go", "Reset password", ["class" => $k, "value" => 1]),
            Ht::submit("cancel", "Cancel"),
            '</div>';
        echo '</form></div>';
        Ht::stash_script("focus_within(\$(\"#homereset\"));window.scroll(0,0)");
    }
}
