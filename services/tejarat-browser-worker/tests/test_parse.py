"""Unit tests for Tejarat worker parsing (no bank / no Playwright)."""

from __future__ import annotations

import time
import unittest
from datetime import datetime
from zoneinfo import ZoneInfo

from worker.parse import (
    TEHRAN_TZ,
    build_credit_transaction,
    classify_calendar_year,
    normalize_transaction_record,
    parse_amount_rial,
    parse_bank_reference,
    parse_occurred_at,
)

CARD = "5859831111222233"


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


class TestCalendarClassification(unittest.TestCase):
    def test_jalali_year(self):
        self.assertEqual(classify_calendar_year(1403), "jalali")

    def test_gregorian_year_2026(self):
        self.assertEqual(classify_calendar_year(2026), "gregorian")

    def test_ambiguous_year(self):
        self.assertIsNone(classify_calendar_year(1700))
        self.assertIsNone(classify_calendar_year(1600))


class TestOccurredAtCalendar(unittest.TestCase):
    def test_valid_jalali_datetime(self):
        ts = parse_occurred_at("1403/05/10", "14:30")
        self.assertIsNotNone(ts)
        expected = int(
            datetime(2024, 7, 31, 14, 30, 0, tzinfo=TEHRAN_TZ).timestamp()
        )
        self.assertEqual(ts, expected)

    def test_valid_gregorian_datetime(self):
        ts = parse_occurred_at("2026-01-15", "10:00")
        self.assertIsNotNone(ts)
        expected = int(
            datetime(2026, 1, 15, 10, 0, 0, tzinfo=TEHRAN_TZ).timestamp()
        )
        self.assertEqual(ts, expected)

    def test_gregorian_slash_not_jalali(self):
        """2026 must not be interpreted as Jalali year 2026."""
        ts = parse_occurred_at("2026/01/15", "10:00")
        self.assertIsNotNone(ts)
        expected = int(
            datetime(2026, 1, 15, 10, 0, 0, tzinfo=TEHRAN_TZ).timestamp()
        )
        self.assertEqual(ts, expected)

    def test_invalid_ambiguous_year(self):
        self.assertIsNone(parse_occurred_at("1700/05/10", "12:00"))

    def test_negative_empty(self):
        self.assertIsNone(parse_occurred_at("", ""))


class TestTehranTimezone(unittest.TestCase):
    def test_tehran_independent_of_server_tz(self):
        """Anchor noon Tehran to a fixed UTC instant (+3:30)."""
        ts = parse_occurred_at("2024-06-01", "12:00")
        self.assertIsNotNone(ts)
        expected = int(
            datetime(2024, 6, 1, 12, 0, 0, tzinfo=TEHRAN_TZ).timestamp()
        )
        self.assertEqual(ts, expected)
        utc_dt = datetime.fromtimestamp(ts, tz=ZoneInfo("UTC"))
        self.assertEqual(utc_dt.hour, 8)
        self.assertEqual(utc_dt.minute, 30)

    def test_midnight_crossing_tehran(self):
        before = parse_occurred_at("1403/12/29", "23:50")
        after = parse_occurred_at("1404/01/01", "00:10")
        self.assertIsNotNone(before)
        self.assertIsNotNone(after)
        assert after is not None and before is not None
        self.assertLess(before, after)
        self.assertGreater(after - before, 0)
        self.assertLess(after - before, 48 * 3600)


class TestStableDuplicateTransaction(unittest.TestCase):
    def test_same_bank_fields_same_id_and_time(self):
        kwargs = dict(
            reference_raw="556677889900",
            amount_raw="250,000 ریال",
            date_raw="1403/07/01",
            time_raw="09:15",
            destination_card_raw=CARD,
        )
        a = build_credit_transaction(**kwargs)
        b = build_credit_transaction(**kwargs)
        self.assertIsNotNone(a)
        self.assertIsNotNone(b)
        assert a is not None and b is not None
        self.assertEqual(a["transaction_id"], b["transaction_id"])
        self.assertEqual(a["occurred_at"], b["occurred_at"])
        self.assertEqual(a["amount"], b["amount"])


class TestBuildCreditTransaction(unittest.TestCase):
    def test_positive_full_row(self):
        tx = build_credit_transaction(
            reference_raw="998877665544",
            amount_raw="500,000 ریال",
            date_raw="1403/06/01",
            time_raw="10:15",
            destination_card_raw=CARD,
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
                destination_card_raw=CARD,
            )
        )

    def test_negative_bad_amount(self):
        self.assertIsNone(
            build_credit_transaction(
                reference_raw="112233445566",
                amount_raw="500,000",
                date_raw="1403/06/01",
                destination_card_raw=CARD,
            )
        )


class TestFixtureNormalize(unittest.TestCase):
    def test_fixture_shape(self):
        tx = normalize_transaction_record(
            {
                "amount": 1497890,
                "destination_card": CARD,
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
                    "destination_card": CARD,
                    "transaction_id": "row-9-123",
                    "occurred_at": 1700000100,
                }
            )
        )


if __name__ == "__main__":
    unittest.main()
