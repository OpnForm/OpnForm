"""Synthetic infrastructure identities; never copy real controller values here."""
def configure_test_target(deploy):
    deploy.ENVIRONMENT = 'v' * 24
    deploy.SERVER = 's' * 24
    deploy.TEAM_ID = 7
    deploy.APPS = {role: 'v' * 23 + str(i) for i, role in enumerate(deploy.APPS)}
    deploy.LEGACY_SERVICE = 'l' * 24
    deploy.LEGACY_ENVIRONMENT_ID = 23
    deploy.LEGACY_SERVER_ID = 11
