// Import utils
import helper from '@utils/helpers';
import testContext from '@utils/testContext';

// Import commonTests
import loginCommon from '@commonTests/BO/loginBO';

// Import pages
import orderMessagesPage from '@pages/BO/customerService/orderMessages';
import addOrderMessagePage from '@pages/BO/customerService/orderMessages/add';

// Import data
import OrderMessageData from '@data/faker/orderMessage';

import {expect} from 'chai';
import type {BrowserContext, Page} from 'playwright';
import {boDashboardPage} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_customerService_orderMessages_CRUDOrderMessage';

/*
Create order message
Update order message
Delete order message
 */
describe('BO - Customer Service - Order Messages : CRUD order message', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let numberOfOrderMessages: number = 0;

  const createOrderMessageData: OrderMessageData = new OrderMessageData();
  const editOrderMessageData: OrderMessageData = new OrderMessageData();

  // before and after functions
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

  it('should go to \'Customer Service > Order Messages\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToOrderMessagesPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.customerServiceParentLink,
      boDashboardPage.orderMessagesLink,
    );
    await orderMessagesPage.closeSfToolBar(page);

    const pageTitle = await orderMessagesPage.getPageTitle(page);
    expect(pageTitle).to.contains(orderMessagesPage.pageTitle);
  });

  it('should reset all filters', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetFirst', baseContext);

    numberOfOrderMessages = await orderMessagesPage.resetAndGetNumberOfLines(page);
    expect(numberOfOrderMessages).to.be.above(0);
  });

  // 1: Create order message
  describe('Create order message', async () => {
    it('should go to new order message page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToNewOrderMessagePage', baseContext);

      await orderMessagesPage.goToAddNewOrderMessagePage(page);

      const pageTitle = await addOrderMessagePage.getPageTitle(page);
      expect(pageTitle).to.contains(addOrderMessagePage.pageTitle);
    });

    it('should create order message', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'createOrderMessage', baseContext);

      const result = await addOrderMessagePage.addEditOrderMessage(page, createOrderMessageData);
      expect(result).to.equal(orderMessagesPage.successfulCreationMessage);
    });

    it('should reset filters and check number of order messages', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterCreate', baseContext);

      const numberOfOrderMessagesAfterReset = await orderMessagesPage.resetAndGetNumberOfLines(page);
      expect(numberOfOrderMessagesAfterReset).to.be.equal(numberOfOrderMessages + 1);
    });
  });

  // 2: Update order message
  describe('Update order message', async () => {
    it(`should filter by name '${createOrderMessageData.name}'`, async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToUpdate', baseContext);

      await orderMessagesPage.filterTable(page, 'name', createOrderMessageData.name);

      const numberOfOrderMessagesAfterFilter = await orderMessagesPage.getNumberOfElementInGrid(page);
      expect(numberOfOrderMessagesAfterFilter).to.be.at.most(numberOfOrderMessages + 1);

      const textColumn = await orderMessagesPage.getTextColumnFromTable(page, 1, 'name');
      expect(textColumn).to.contains(createOrderMessageData.name);
    });

    it('should go to edit first order message page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToEditPage', baseContext);

      await orderMessagesPage.gotoEditOrderMessage(page, 1);

      const pageTitle = await addOrderMessagePage.getPageTitle(page);
      expect(pageTitle).to.contains(addOrderMessagePage.pageTitleEdit);
    });

    it('should edit order message', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'updateOrderMessage', baseContext);

      const result = await addOrderMessagePage.addEditOrderMessage(page, editOrderMessageData);
      expect(result).to.equal(orderMessagesPage.successfulUpdateMessage);
    });

    it('should reset filters and check number of order messages', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterUpdate', baseContext);

      const numberOfOrderMessagesAfterReset = await orderMessagesPage.resetAndGetNumberOfLines(page);
      expect(numberOfOrderMessagesAfterReset).to.be.equal(numberOfOrderMessages + 1);
    });
  });

  // 3: Delete order message
  describe('Delete order message', async () => {
    it(`should filter by name '${editOrderMessageData.name}'`, async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToDelete', baseContext);

      await orderMessagesPage.filterTable(page, 'name', editOrderMessageData.name);

      const numberOfOrderMessagesAfterFilter = await orderMessagesPage.getNumberOfElementInGrid(page);
      expect(numberOfOrderMessagesAfterFilter).to.be.at.most(numberOfOrderMessages + 1);

      const textColumn = await orderMessagesPage.getTextColumnFromTable(page, 1, 'name');
      expect(textColumn).to.contains(editOrderMessageData.name);
    });

    it('should delete order message', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'deleteOrderMessage', baseContext);

      // delete order message in first row
      const result = await orderMessagesPage.deleteOrderMessage(page, 1);
      expect(result).to.be.equal(orderMessagesPage.successfulDeleteMessage);
    });

    it('should reset filters and check number of order messages', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterDelete', baseContext);

      const numberOfOrderMessagesAfterReset = await orderMessagesPage.resetAndGetNumberOfLines(page);
      expect(numberOfOrderMessagesAfterReset).to.be.equal(numberOfOrderMessages);
    });
  });
});
