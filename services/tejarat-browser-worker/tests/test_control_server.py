"""No live bank or browser required; verify login controller security decisions."""
import os
import unittest
from unittest.mock import patch
from worker import control_server as ctrl


class StubLocator:
    def __init__(self, count):
        self.count_value = count
    def count(self):
        return self.count_value


class ControllerTests(unittest.TestCase):
    def test_official_https_only(self):
        self.assertTrue(ctrl.trusted("https://hb.tejaratbank.ir/account/log"))
        for url in [
            "http://hb.tejaratbank.ir/login", "https://evil.tejaratbank.ir/login",
            "https://hb.tejaratbank.ir.evil.example/login", "https://hb.tejaratbank.ir:8443/login",
            "https://hb.tejaratbank.ir:bad/login", "file:///etc/passwd"
        ]:
            self.assertFalse(ctrl.trusted(url), url)

    def test_statement_only_verified_with_specific_ui(self):
        self.assertEqual(ctrl.classify("https://hb.tejaratbank.ir/account/log", "گردش حساب | گزارش | نمایش"), "session_verified")
        self.assertNotEqual(ctrl.classify("https://hb.tejaratbank.ir/account/log", "گردش حساب | گزارش"), "session_verified")
        self.assertEqual(ctrl.classify("https://hb.tejaratbank.ir/register", "فعال سازی"), "awaiting_first_activation")
        self.assertEqual(ctrl.classify("https://hb.tejaratbank.ir/login", "ورود"), "awaiting_bank_login")
        self.assertEqual(ctrl.classify("https://hb.tejaratbank.ir.evil.example/login", ""), "untrusted_redirect")

    def test_invalid_commands_not_executed(self):
        self.assertEqual(ctrl.dispatch({"action":"delete"})["code"], "invalid_action")
        self.assertEqual(ctrl.dispatch({"action":"start", "username":"", "password":"x"})["code"], "credentials_missing")
        self.assertEqual(ctrl.dispatch({"action":"start", "username":"u", "password":""})["code"], "credentials_missing")

    def test_status_excludes_credentials(self):
        data = ctrl.dispatch({"action":"status", "password":"sensitive", "username":"secret"})
        self.assertTrue(data["ok"])
        self.assertNotIn("username", data)
        self.assertNotIn("password", data)

    def test_form_autofill_is_fail_closed_when_not_password_login(self):
        class Page:
            url = "https://hb.tejaratbank.ir/register"
            def locator(self, *args, **kwargs):
                raise AssertionError("registration DOM must not be submitted by auto-login")
        ctrl._fill_existing_login(Page(), "u", "p")


if __name__ == "__main__":
    unittest.main()
