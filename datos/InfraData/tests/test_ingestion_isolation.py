"""The integration guard rejects aliases and credentials for protected databases."""

import pytest

from conftest import check_test_database


@pytest.mark.parametrize("host", ["localhost", "127.0.0.1", "[::1]", "some-proxy"])
def test_protected_name_rejected_before_connection(host):
    with pytest.raises(ValueError, match="otro nombre"):
        check_test_database(f"postgresql://other:password@{host}:5433/infradata")
