import logging

from models import SessionLocal
from services.startup_service import run_preprod_bootstrap


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s [%(name)s] %(message)s",
)


def main() -> None:
    db = SessionLocal()
    try:
        run_preprod_bootstrap(db)
    finally:
        db.close()


if __name__ == "__main__":
    main()