// Import utils
import helper from '@utils/helpers';
import testContext from '@utils/testContext';

// Import commonTests
import loginCommon from '@commonTests/BO/loginBO';

// Import pages
import viewCustomerPage from '@pages/BO/customers/view';
import ordersPage from '@pages/BO/orders';

import {
  boDashboardPage,
  // Import data
  dataCustomers,
} from '@prestashop-core/ui-testing';

import {expect} from 'chai';
import type {BrowserContext, Page} from 'playwright';

const baseContext: string = 'functional_BO_orders_orders_viewCustomer';

/*
Go to orders page
Filter by customer name 'J. DOE'
Click on customer link on grid
Check that View customer page is displayed
 */
describe('BO - Orders : View customer from orders page', async () => {
  let browserContext: BrowserContext;
  let page: Page;

  before(async function () {
    browserContext = await helper.createBrowserContext(this.browser);
    page = await helper.newTab(browserContext);
  });

  after(async () => {
    await helper.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await loginCommon.loginBO(this, page);
  });

  it('should go to \'Orders > Orders\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToOrdersPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.ordersLink,
    );
    await ordersPage.closeSfToolBar(page);

    const pageTitle = await ordersPage.getPageTitle(page);
    expect(pageTitle).to.contains(ordersPage.pageTitle);
  });

  it('should reset all filters', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetFilters', baseContext);

    const numberOfOrders = await ordersPage.resetAndGetNumberOfLines(page);
    expect(numberOfOrders).to.be.above(0);
  });

  it('should filter order by customer name', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'filterByCustomer', baseContext);

    await ordersPage.filterOrders(
      page,
      'input',
      'customer',
      dataCustomers.johnDoe.lastName,
    );

    const numberOfOrders = await ordersPage.getNumberOfElementInGrid(page);
    expect(numberOfOrders).to.be.at.least(1);
  });

  it('should check customer link', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'viewCustomer', baseContext);

    // Click on customer link first row
    page = await ordersPage.viewCustomer(page, 1);

    const pageTitle = await viewCustomerPage.getPageTitle(page);
    expect(pageTitle).to
      .eq(viewCustomerPage.pageTitle(`${dataCustomers.johnDoe.firstName[0]}. ${dataCustomers.johnDoe.lastName}`));
  });

  it('should go back to \'Orders > Orders\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goBackToOrdersPageToResetFilter', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.ordersLink,
    );

    const pageTitle = await ordersPage.getPageTitle(page);
    expect(pageTitle).to.contains(ordersPage.pageTitle);
  });

  it('should reset all filters', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetFiltersAfterCheck', baseContext);

    const numberOfOrders = await ordersPage.resetAndGetNumberOfLines(page);
    expect(numberOfOrders).to.be.above(0);
  });
});
