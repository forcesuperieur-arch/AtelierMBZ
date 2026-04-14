"""Shared helpers for statistiques API."""

from datetime import date, timedelta

from auth import User


def tenant_id(current_user: User) -> int:
    return int(getattr(current_user, "atelier_id", None) or 1)


def get_date_range(periode: str) -> tuple:
    today = date.today()
    if periode == "jour":
        return today, today
    if periode == "semaine":
        debut = today - timedelta(days=today.weekday())
        return debut, today
    if periode == "mois":
        debut = today.replace(day=1)
        return debut, today
    if periode == "annee":
        debut = today.replace(month=1, day=1)
        return debut, today
    return today, today
