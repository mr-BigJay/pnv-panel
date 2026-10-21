import json
import time
import unittest
from unittest.mock import Mock, patch
from worker.pnv_client import publish, sign

CARD = "5859831122334455"
TX = {"transaction_id": "TJ-987654321",
      "amount_rial": 1499280, "destination_card": CARD,
      "occurred_at": 1792512000, "direction": "credit"}
URL = "https://panel.example/bank-transaction-webhook.php"
SECRET = "a" * 64

class SignedFeedTest(unittest.TestCase):
    def test_deterministic_hmac(self):
        ts = 1700000000
        payload = b'{"amount_rial":1234}'
        import hmac, hashlib
        expected = hmac.new(SECRET.encode(), str(ts).encode()+b"\n"+payload, hashlib.sha256).hexdigest()
        self.assertEqual(sign(ts,payload,SECRET), expected)

    def test_bad_session_blocked_without_network(self):
        with patch("worker.pnv_client.requests.post") as req:
            self.assertEqual(publish(URL, SECRET, TX, session_ok=False)[1], "session_not_verified")
            req.assert_not_called()

    def test_non_https_blocked_without_network(self):
        with patch("worker.pnv_client.requests.post") as req:
            self.assertEqual(publish("http://panel.example/hook", SECRET, TX, session_ok=True)[1], "https_endpoint_required")
            req.assert_not_called()

    def test_debit_blocked_without_network(self):
        with patch("worker.pnv_client.requests.post") as req:
            self.assertEqual(publish(URL, SECRET, {**TX,"direction":"debit"}, session_ok=True)[1], "not_credit")
            req.assert_not_called()

    def test_authenticated_credit_published(self):
        response=Mock(status_code=200)
        response.json.return_value={"ok":True,"dry_run":True}
        with patch("worker.pnv_client.requests.post", return_value=response) as req:
            ok, code=publish(URL,SECRET,TX,now=1700000000,session_ok=True)
            self.assertTrue(ok)
            self.assertEqual(code,"dry_run")
            args=req.call_args
            body=args.kwargs["data"]
            headers=args.kwargs["headers"]
            self.assertEqual(headers["X-PNV-Signature"], sign(1700000000,body,SECRET))
            data=json.loads(body)
            self.assertEqual(data,TX)
            self.assertNotIn("password",data)
            self.assertFalse(args.kwargs["allow_redirects"])

    def test_fixture_mode_cannot_submit_even_with_live_endpoint(self):
        from worker.main import run_once
        import os
        with patch.dict(os.environ, {
            "TEJARAT_COLLECT_MODE": "fixture",
            "PNV_BANK_FEED_URL": URL,
            "PNV_BANK_FEED_SECRET": SECRET,
        }):
            with patch("worker.main.collect_transactions") as collect:
                with patch("worker.main.publish") as send:
                    self.assertEqual(run_once(), 2)
                    collect.assert_not_called()
                    send.assert_not_called()

    def test_redirect_not_followed(self):
        response=Mock(status_code=302)
        with patch("worker.pnv_client.requests.post", return_value=response):
            self.assertEqual(publish(URL,SECRET,TX,session_ok=True)[1], "redirect_refused")

if __name__ == "__main__":
    unittest.main()
