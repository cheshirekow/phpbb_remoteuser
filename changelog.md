# v0.1 series

## v0.1.1 -- in progress

* add travis badge to README
* fix typo in .gitattributes
* fix remoteuser settings shown on ACP even if not active
* add functional test for provider init() sanity check failure
* use @vender_extension construct to correctly refer to acp template path
* change acp template to swig format
* purge cache after changing auth provider in functional tests
* use language->lang() instead of user->lang[] to get localized text
* declare parent relationship in dependency injection yaml instead of
  forwarding c'tor arguments
* generate more secure passwords
