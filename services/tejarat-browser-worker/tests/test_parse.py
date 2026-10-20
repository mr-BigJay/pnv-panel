"""Unit tests for Tejarat worker parsing (no bank / no Playwright)."""

from __future__ import annotations

import time
import unittest

from worker.parse import (
    build_credit_transaction,
    normalize_transaction_record,
    parse_amount_rial,
    parse_bank_reference,
    parse_occurred_at,
)


class TestBankReference(unittest.TestCase):
    def test_positive_numeric_ref(self):
        self.assertEqual(parse_bank_reference("881122334455"), "881122334455")

    def test_negative_row_synthetic(self):
        self.assertIsNone(parse_bank_reference("row-3-1700000000"))

    def test_negative_empty(self):
        self.assertIsNone(parse_bank_reference("   "))


class TestAmountRial(unittest.TestCase):
    def test_positive_rial(self):
        amount, err = parse_amount_rial("1,497,890 ریال")
        self.assertIsNone(err)
        self.assertEqual(amount, 1497890)

    def test_positive_toman(self):
        amount, err = parse_amount_rial("149,789 تومان")
        self.assertIsNone(err)
        self.assertEqual(amount, 1497890)

    def test_negative_missing_unit(self):
        amount, err = parse_amount_rial("1497890")
        self.assertEqual(err, "amount_unit_missing")
        self.assertEqual(amount, 0)

    def test_negative_ambiguous_unit(self):
        amount, err = parse_amount_rial("100 ریال 50 تومان")
        self.assertEqual(err, "amount_unit_ambiguous")
        self.assertEqual(amount, 0)


class TestOccurredAt(unittest.TestCase):
    def test_jalali_datetime(self):
        ts = parse_occurred_at("1403/05/10", "14:30")
        self.assertIsNotNone(ts)
        self.assertLess(ts, time.time() + 60)

    def test_negative_empty(self):
        self.assertIsNone(parse_occurred_at("", ""))


class TestBuildCreditTransaction(unittest.TestCase):
    def test_positive_full_row(self):
        tx = build_credit_transaction(
            reference_raw="998877665544",
            amount_raw="500,000 ریال",
            date_raw="1403/06/01",
            time_raw="10:15",
            destination_card_raw="5859831111222233",
        )
        self.assertIsNotNone(tx)
        assert tx is not None
        self.assertEqual(tx["transaction_id"], "998877665544")
        self.assertEqual(tx["amount"], 500000)
        self.assertGreater(tx["occurred_at"], 0)
        self.assertNotEqual(tx["occurred_at"], tx["source_session_at"])

    def test_negative_no_reference(self):
        self.assertIsNone(
            build_credit_transaction(
                reference_raw="",
                amount_raw="500,000 ریال",
                date_raw="1403/06/01",
                destination_card_raw="5859831111222233",
            )
        )

    def test_negative_bad_amount(self):
        self.assertIsNone(
            build_credit_transaction(
                reference_raw="112233445566",
                amount_raw="500,000",
                date_raw="1403/06/01",
                destination_card_raw="5859831111222233",
            )
        )


class TestFixtureNormalize(unittest.TestCase):
    def test_fixture_shape(self):
        tx = normalize_transaction_record(
            {
                "amount": 1497890,
                "destination_card": "5859831111222233",
                "transaction_id": "FIX-TJ-001",
                "occurred_at": 1700000100,
                "direction": "credit",
            }
        )
        self.assertIsNotNone(tx)
        assert tx is not None
        self.assertEqual(tx["transaction_id"], "FIX-TJ-001")

    def test_reject_synthetic_id(self):
        self.assertIsNone(
            normalize_transaction_record(
                {
                    "amount": 1497890,
                    "destination_card": "5859831111222233",
                    "transaction_id": "row-9-123",
                    "occurred_at": 1700000100,
                }
            )
        )


if __name__ == "__main__":
    unittest.main()
