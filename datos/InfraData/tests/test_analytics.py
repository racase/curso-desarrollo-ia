"""Validación de fechas sin necesidad de PostgreSQL."""

from datetime import UTC, date, datetime

import pytest

from infradata import analytics


@pytest.mark.parametrize(
    ("since", "until"),
    [
        (date(2026, 9, 2), date(2026, 9, 1)),
        (None, date.max),
        ("2026-09-01", None),
        (datetime(2026, 9, 1), None),
    ],
)
def test_invalid_dates_do_not_connect(monkeypatch, since, until):
    def unexpected_connection():
        pytest.fail("No debe conectar con fechas inválidas")

    monkeypatch.setattr(analytics, "connect", unexpected_connection)
    with pytest.raises(ValueError):
        analytics.daily_summary(since=since, until=until)


def test_inclusive_date_bounds_are_utc():
    assert analytics.date_bounds(date(2026, 9, 1), date(2026, 9, 1)) == (
        datetime(2026, 9, 1, tzinfo=UTC),
        datetime(2026, 9, 2, tzinfo=UTC),
    )
    assert analytics.date_bounds(None, None) == (None, None)
