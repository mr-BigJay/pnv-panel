"""
Normalize Tejarat statement rows for auto-verify (stable bank fields only).
"""

from __future__ import annotations

import re
import time
from datetime import datetime
from typing import Any, Literal
from zoneinfo import ZoneInfo

TEHRAN_TZ = ZoneInfo("Asia/Tehran")

REF_MIN_LEN = 6
REF_MAX_LEN = 64
AMOUNT_MIN_RIAL = 1000
AMOUNT_MAX_RIAL = 5_000_000_000

JALALI_YEAR_MIN = 1300
JALALI_YEAR_MAX = 1500
GREGORIAN_YEAR_MIN = 1900
GREGORIAN_YEAR_MAX = 2100

REF_REJECT_PREFIXES = ("row-", "tmp-", "fake-")


def _digits_only(raw: str) -> str:
    return re.sub(r"\D+", "", raw or "")


def classify_calendar_year(year: int) -> Literal["jalali", "gregorian"] | None:
    if JALALI_YEAR_MIN <= year <= JALALI_YEAR_MAX:
        return "jalali"
    if GREGORIAN_YEAR_MIN <= year <= GREGORIAN_YEAR_MAX:
        return "gregorian"
    return None


def _tehran_unix(gy: int, gm: int, gd: int, hh: int, mm: int, ss: int) -> int | None:
    try:
        dt = datetime(gy, gm, gd, hh, mm, ss, tzinfo=TEHRAN_TZ)
    except ValueError:
        return None
    ts = int(dt.timestamp())
    if ts <= 0 or ts > time.time() + 86400:
        return None
    return ts


def parse_bank_reference(raw: str) -> str | None:
    text = re.sub(r"\s+", "", (raw or "").strip())
    if not text:
        return None
    lower = text.lower()
    for prefix in REF_REJECT_PREFIXES:
        if lower.startswith(prefix):
            return None
    if re.fullmatch(r"\d{6,32}", text):
        return text[:REF_MAX_LEN]
    if re.fullmatch(r"[A-Za-z0-9\-]{8,64}", text):
        return text[:REF_MAX_LEN]
    cleaned = re.sub(r"[^\w\-]", "", text)
    if len(cleaned) >= REF_MIN_LEN and re.search(r"\d{4,}", cleaned):
        return cleaned[:REF_MAX_LEN]
    return None


def parse_amount_rial(raw: str) -> tuple[int, str | None]:
    """
    Returns (amount_rial, error_code). Detects explicit ریال/تومان markers.
    """
    text = (raw or "").strip()
    if not text:
        return 0, "amount_empty"

    norm = text.replace("٬", ",").replace("،", ",")
    lower = norm.lower()
    has_rial = "ریال" in norm or "ريال" in norm or "rial" in lower
    has_toman = "تومان" in norm or "toman" in lower

    if has_rial and has_toman:
        return 0, "amount_unit_ambiguous"

    num_match = re.search(r"(\d{1,3}(?:,\d{3})+|\d+)", norm)
    if not num_match:
        return 0, "amount_unparseable"

    digits = _digits_only(num_match.group(1))
    if not digits:
        return 0, "amount_unparseable"

    value = int(digits)

    if has_toman:
        value *= 10
    elif not has_rial:
        return 0, "amount_unit_missing"

    if value < AMOUNT_MIN_RIAL or value > AMOUNT_MAX_RIAL:
        return 0, "amount_out_of_range"

    return value, None


def _jalali_to_gregorian(jy: int, jm: int, jd: int) -> tuple[int, int, int]:
    """Convert Jalali date to Gregorian (algorithm for 1178-1633 jalali years)."""
    jy -= 979
    jm -= 1
    jd -= 1
    j_day_no = 365 * jy + jy // 33 * 8 + (jy % 33 + 3) // 4
    for i in range(jm):
        j_day_no += 31 if i < 6 else 30
    j_day_no += jd
    g_day_no = j_day_no + 79
    gy = 1600 + 400 * (g_day_no // 146097)
    g_day_no %= 146097
    leap = True
    if g_day_no >= 36525:
        g_day_no -= 1
        gy += 100 * (g_day_no // 36524)
        g_day_no %= 36524
        if g_day_no >= 365:
            g_day_no += 1
        else:
            leap = False
    gy += 4 * (g_day_no // 1461)
    g_day_no %= 1461
    if g_day_no >= 366:
        leap = False
        g_day_no -= 1
        gy += g_day_no // 365
        g_day_no %= 365
    sal_a = [0, 31, 28 + int(leap), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    gm = 0
    while gm < 13 and g_day_no >= sal_a[gm]:
        g_day_no -= sal_a[gm]
        gm += 1
    return gy, gm, g_day_no + 1


def _parse_slash_date(y: int, mo: int, d: int, hh: int, mm: int, ss: int) -> int | None:
    if mo < 1 or mo > 12 or d < 1 or d > 31:
        return None
    if hh < 0 or hh > 23 or mm < 0 or mm > 59 or ss < 0 or ss > 59:
        return None

    calendar = classify_calendar_year(y)
    if calendar is None:
        return None

    if calendar == "jalali":
        if mo > 12 or d > 31:
            return None
        gy, gm, gd = _jalali_to_gregorian(y, mo, d)
    else:
        gy, gm, gd = y, mo, d

    return _tehran_unix(gy, gm, gd, hh, mm, ss)


def parse_occurred_at(date_raw: str, time_raw: str = "") -> int | None:
    date_raw = (date_raw or "").strip()
    time_raw = (time_raw or "").strip()
    combined = f"{date_raw} {time_raw}".strip()

    if not combined:
        return None

    if re.fullmatch(r"\d{10,12}", combined.replace(" ", "")):
        ts = int(combined.replace(" ", ""))
        if ts > 1_000_000_000_000:
            ts //= 1000
        if 946684800 <= ts <= time.time() + 86400:
            return ts

    m = re.search(
        r"(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?",
        combined,
    )
    if m:
        y, mo, d = int(m.group(1)), int(m.group(2)), int(m.group(3))
        hh = int(m.group(4) or 0)
        mm = int(m.group(5) or 0)
        ss = int(m.group(6) or 0)
        return _parse_slash_date(y, mo, d, hh, mm, ss)

    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M", "%Y-%m-%d"):
        try:
            naive = datetime.strptime(combined[:19], fmt)
        except ValueError:
            continue
        calendar = classify_calendar_year(naive.year)
        if calendar is None:
            return None
        if calendar == "jalali":
            gy, gm, gd = _jalali_to_gregorian(naive.year, naive.month, naive.day)
        else:
            gy, gm, gd = naive.year, naive.month, naive.day
        return _tehran_unix(gy, gm, gd, naive.hour, naive.minute, naive.second)

    return None


def normalize_destination_card(raw: str) -> str | None:
    card = _digits_only(raw)
    if len(card) < 4:
        return None
    return card


def build_credit_transaction(
    *,
    reference_raw: str,
    amount_raw: str,
    date_raw: str,
    time_raw: str = "",
    destination_card_raw: str,
    description: str = "",
) -> dict[str, Any] | None:
    ref = parse_bank_reference(reference_raw)
    amount, amount_err = parse_amount_rial(amount_raw)
    occurred = parse_occurred_at(date_raw, time_raw)
    card = normalize_destination_card(destination_card_raw)

    if not ref or amount_err or amount <= 0 or not occurred or not card:
        return None

    return {
        "amount": amount,
        "amount_unit": "rial",
        "destination_card": card,
        "transaction_id": ref,
        "occurred_at": occurred,
        "description": (description or date_raw)[:120],
        "direction": "credit",
        "source_session_at": int(time.time()),
    }


def normalize_transaction_record(record: dict[str, Any]) -> dict[str, Any] | None:
    """Validate fixture / pre-shaped JSON (no synthetic ids)."""
    if not isinstance(record, dict):
        return None

    ref = parse_bank_reference(
        str(record.get("transaction_id") or record.get("reference") or record.get("id") or "")
    )
    amount_val = record.get("amount") or record.get("amount_rial")
    if ref and isinstance(amount_val, int) and amount_val >= AMOUNT_MIN_RIAL:
        amount = int(amount_val)
        amount_err = None
        unit = str(record.get("amount_unit") or "rial").lower()
        if unit == "toman":
            amount *= 10
        elif unit not in ("rial", ""):
            amount_err = "amount_unit_invalid"
    else:
        amount, amount_err = parse_amount_rial(str(record.get("amount_text") or amount_val or ""))

    occurred = int(record.get("occurred_at") or record.get("timestamp") or 0)
    if occurred <= 0:
        parsed = parse_occurred_at(
            str(record.get("date") or record.get("description") or ""),
            str(record.get("time") or ""),
        )
        occurred = parsed if parsed is not None else 0

    card = normalize_destination_card(
        str(record.get("destination_card") or record.get("card") or "")
    )

    if not ref or amount_err or amount <= 0 or occurred <= 0 or not card:
        return None

    direction = str(record.get("direction") or "credit").lower()
    if direction not in ("credit", "in", "deposit", ""):
        return None

    return {
        "amount": amount,
        "amount_unit": "rial",
        "destination_card": card,
        "transaction_id": ref,
        "occurred_at": occurred,
        "description": str(record.get("description") or record.get("text") or "")[:120],
        "direction": "credit",
        "source_session_at": int(record.get("source_session_at") or time.time()),
    }
