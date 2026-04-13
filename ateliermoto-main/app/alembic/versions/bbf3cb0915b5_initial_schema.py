"""initial schema

Revision ID: bbf3cb0915b5
Revises: 
Create Date: 2026-03-31 22:35:25.203292

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from models import Base


# revision identifiers, used by Alembic.
revision: str = 'bbf3cb0915b5'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    bind = op.get_bind()
    Base.metadata.create_all(bind=bind)


def downgrade() -> None:
    bind = op.get_bind()
    Base.metadata.drop_all(bind=bind)
