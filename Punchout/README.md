# TireHub Punchout - QA Testing Guide

## What is PunchOut?

PunchOut is a procurement integration protocol that allows users from a procurement system to "punch out" to our eCommerce store, shop, and then return to their procurement system with their cart/order details. The entire process uses the cXML (Commerce XML) format for data exchange.

## Punchout Flow Overview

The punchout process follows this general flow:

1. **Initial Request**: The procurement system sends a cXML request to TireHub
2. **Authentication**: TireHub validates the credentials and partner information
3. **User Experience**: The user is directed to either:
    - Address selection page (if no valid address was provided)
    - Direct shopping experience (if valid address was provided)
4. **Shopping**: User browses and adds products to cart
5. **Return**: User clicks "Return to Procurement System" to send cart data back

## Key Components to Test

### 1. Entry Points

There are two main entry points to the punchout system:

#### A. Pre-PunchOut Item Setup (Optional) (NOT READY FOR TESTING)
- **Endpoint**: `/punchout/setup/item`
- **Purpose**: Save items that customer wants to buy before the punchout process
- **Required Parameters**:
    - `dealerCode` - The code identifying the dealer
    - `partnerIdentity` - The identity of the punchout partner
    - `itemId` - Product to be added to cart
    - `quantityNeeded` - Quantity of product

#### B. PunchOut Request Processing
- **Endpoint**: `/punchout/setup/request`
- **Purpose**: Process cXML request from procurement system
- **Method**: POST
- **Content**: cXML document (see sample in test scenarios)

### 2. Address Handling

How TireHub handles ShipTo addressId information:

- Each partner has a `dealerPrefix` value (e.g., "CVN_" for Carvana) from `api/cache/getpunchoutpartners`
- We take addressId (e.g., 123456) and combine with dealer prefix (CVN_123456)
- For CarMax with addressId length equal or greater then 6, we take only 4 digits start from second (123456 → 2345) and add dealer prefix CMX_
- If address equal or greater then 5 and starts from 0 and `trimLeadingZeroFromDealerCode` from `api/cache/getpunchoutpartners` is tru
  - we remove 0 and take 4 next digits (012345 → 1234) 
- Received addressId we validate via `/api/cache/lookupdealers`
  - from result we take `shipToLocation`->`locationId` which will be used as a standard dealerCode for us and for creating customer

### 3. Flow Scenarios

There are two main flow scenarios to test:

#### A. Address Selection Flow
- Triggered when ShipTo->addressID is empty or dealer not found
- User is sent to `/punchout/portal` to select an address
- After address selection, a customer account is created
- User is redirected to the main shopping page in PunchOut mode

#### B. Direct Shopping Flow
- Triggered when ShipTo->addressID is valid
- Customer account is created directly
- User is sent to `/punchout/shopping/start`
- User is redirected to the main shopping page in PunchOut mode
- for CarMax automatically adding to cart items from Pre-PunchOut Item Setup (NOT READY FOR TESTING)

### 4. Return Process
- When user finishes shopping, they click "Trasfer Cart"
- We follow standard process with creating magento and API order via `createorderstructure`
  - `poNum` generate random uniq string which starts from TEMMPO
    - length of this field should be 44 symbols or less
- Cart data is formatted as cXML
- Terminate customer session
  - `erp_order_number` and `temmpo` reflects in punchout session table for debugging
- User is redirected back to procurement system via `BrowserFormPost->URL`
- Data includes all cart items with necessary information for ordering


## Testing Endpoints

Use these endpoints for testing different stages of the punchout process:

1. **Pre-PunchOut Setup**:
    - URL: `/punchout/setup/item?dealerCode=111111&partnerIdentity=test&itemId=test&quantityNeeded=1`
    - Can be accessed directly in browser for testing
    - as a result should return BuyerCookie which should be used for next step

2. **cXML Processing**:
    - URL: `/punchout/setup/request`
    - Requires cXML POST request (use Postman or testing tool)

3. **Address Selection**:
    - URL: `/punchout/portal?cookie=99df85a3c958a552b26ba6dc04a9242f`
    - Requires a valid BuyerCookie from previous cXML request
    - not able for direct access since buyer cookie now is encrypted due to security

4. **Shopping Start**:
    - URL: `/punchout/shopping/start?cookie=99df85a3c958a552b26ba6dc04a9242f`
    - Requires a valid BuyerCookie from previous cXML request
    - not able for direct access since buyer cookie now is encrypted due to security

## Test Scenarios

### 1. Basic PunchOut Setup Request

**Objective**: Verify the system correctly processes a valid PunchOutSetupRequest.

**Steps**:
1. Send cXML PunchOutSetupRequest to `/punchout/setup/request`
2. Verify response has 200 status code
3. Verify response contains valid PunchOutSetupResponse with URL
4. Access URL and verify landing on store in PunchOut mode

**Expected Result**:
- XML response contains valid StartPage URL
- URL loads store in PunchOut mode

**Sample Request**:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
<cXML xml:lang="en-US" payloadID="1726950849.0571427@prd760app55.int.coupahost.com" timestamp="2024-09-21T16:34:09-04:00">
    <Header>
        <From>
            <Credential domain="NetworkID">
                <Identity>CarMax</Identity>
            </Credential>
        </From>
        <To>
            <Credential domain="DUNS">
                <Identity>08-125-4817</Identity>
            </Credential>
        </To>
        <Sender>
            <Credential domain="domainFromApi">
                <Identity>identityFromApi</Identity>
                <SharedSecret>SharedSecretFromApi</SharedSecret>
            </Credential>
            <UserAgent>Coupa Procurement 1.0</UserAgent>
        </Sender>
    </Header>
    <Request>
        <PunchOutSetupRequest operation="create">
            <BuyerCookie>99df85a3c958a552b26ba6dc04a9242f</BuyerCookie>
            <Extrinsic name="FirstName">Delaina</Extrinsic>
            <Extrinsic name="LastName">Beaman</Extrinsic>
            <Extrinsic name="UniqueName">203140</Extrinsic>
            <Extrinsic name="UserEmail">Delaina_Beaman@carmax.com</Extrinsic>
            <Extrinsic name="User">203140</Extrinsic>
            <Extrinsic name="BusinessUnit">COUPA</Extrinsic>
            <BrowserFormPost>
                <URL>https://carmax.coupahost.com/punchout/checkout?id=27</URL>
            </BrowserFormPost>
            <Contact role="endUser">
                <Name xml:lang="en-US">203140</Name>
                <Email>Delaina_Beaman@carmax.com</Email>
            </Contact>
            <SupplierSetup>
                <URL>https://customers.web.middleware.tirehub.com/Punchout/setup</URL>
            </SupplierSetup>
            <ShipTo>
                <Address isoCountryCode="US" addressID="171210">
                    <Name xml:lang="en">CarMax</Name>
                    <PostalAddress name="default">
                        <DeliverTo>Delaina Beaman</DeliverTo>
                        <Street>10201 Philadelphia Rd</Street>
                        <Street>CarMax Auto Superstores Inc</Street>
                        <City>White Marsh</City>
                        <State>MD</State>
                        <PostalCode>21162-3401</PostalCode>
                        <Country isoCountryCode="US">United States</Country>
                    </PostalAddress>
                    <Email name="default">Delaina_Beaman@carmax.com</Email>
                    <Phone name="default">
                        <TelephoneNumber>
                            <CountryCode isoCountryCode="US">1</CountryCode>
                            <AreaOrCityCode>410</AreaOrCityCode>
                            <Number>9316500</Number>
                            <Extension/>
                        </TelephoneNumber>
                    </Phone>
                </Address>
            </ShipTo>
        </PunchOutSetupRequest>
    </Request>
</cXML>
```

### 2. Pre-PunchOut Item Setup

**Objective**: Verify pre-punchout item setup works correctly.

**Steps**:
1. Send request to `/punchout/setup/item` with required parameters
2. Initiate PunchOut flow
3. Verify items appear in cart

**Expected Result**:
- Item information successfully saved
- When entering PunchOut session, pre-saved items appear in cart

### 3. Address Selection Flow

**Objective**: Verify address selection process when no addressID is provided.

**Steps**:
1. Send cXML request without ShipTo addressID
2. Verify redirect to `/punchout/portal`
3. Select address from options
4. Verify customer creation with selected dealer code
5. Verify redirect to home page in PunchOut mode

**Expected Result**:
- User directed to address selection page
- After selection, customer created
- User redirected to shopping experience

### 4. Direct Shopping Flow

**Objective**: Verify direct shopping when valid addressID is provided.

**Steps**:
1. Send cXML request with valid ShipTo addressID
2. Verify redirect to `/punchout/shopping/start`
3. Verify customer creation
4. Verify redirect to home page in PunchOut mode

**Expected Result**:
- Customer created directly without address selection
- User redirected directly to shopping experience

### 5. Partner Validation

**Objective**: Verify validation of PunchOut partners.

**Steps**:
1. Send cXML request with valid partner credentials
2. Send cXML request with invalid partner credentials

**Expected Result**:
- Valid partner credentials accepted
- Invalid partner credentials rejected with error

### 6. Return Process Testing

**Objective**: Verify "Transfer Cart" process.

**Steps**:
1. Complete PunchOut session setup
2. Add items to cart
3. Click "Transfer Cart" button
4. Verify cart data format and redirection

**Expected Result**:
- Cart data correctly formatted
- User redirected to procurement system

### 7. Error Handling

**Objective**: Verify handling of error conditions.

**Scenarios to test**:
- Malformed XML request
- Missing required elements
- Invalid partner credentials
- Invalid dealer codes
- System errors during processing

**Expected Result**:
- Appropriate error responses with clear messages
- No server crashes or unexpected behavior

## Debugging Tips

1. Check for proper credentials in the cXML request
2. Ensure dealer code format matches partner expectations
3. Look for valid ShipTo address format
4. Verify BrowserFormPost URL is present and valid
5. Check for BuyerCookie uniqueness
6. If you use the same BuyerCookie again - it triggers error

## Customer creating process

**Email**:
- Format should be `Punchout_dealerCode@tirehub.com` (example `Punchout_123456@tirehub.com`)

**First and Last name**:
- If cXML does not contain Extrinsics for example:
  - `<Extrinsic name="FirstName">Jon</Extrinsic>` and `<Extrinsic name="LastName">Dou</Extrinsic>`
  - we set up First name as `Punchout` and Last name as `User`
