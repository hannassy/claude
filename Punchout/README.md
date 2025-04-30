What is PunchOut?

PunchOut is a procurement integration protocol that allows users from a procurement system to "punch out" to our eCommerce store, shop, and then return to their procurement system with their cart/order details. The entire process uses the cXML (Commerce XML) format for data exchange.

Our Implementation Flow

Our PunchOut implementation has two main entry points:

1. Optional Pre-PunchOut Item Setup

Endpoint: /punchout/setup/item

Purpose: Allows saving items the customer wants to buy before the PunchOut process starts

Required Parameters:

dealerCode

partnerIdentity

Optional Parameters:

itemId

quantityNeeded

2. PunchOut Request Processing

Endpoint: /punchout/setup/request

Purpose: Receives and processes the cXML PunchOutSetupRequest from procurement systems

The flow consists of these steps:

Receive cXML request from procurement system (typically through PunchOut testing tools like
cXML PunchOut Tester )

Validate credentials (domain, identity, sharedSecret) against authorized partners

Partner list is retrieved from /api/cache/getpunchoutpartners

Save details from the cXML request to our database for subsequent steps

Process the ShipTo address information:

each partner has dealerPrefix value in /api/cache/getpunchoutpartners

for Carvana - "dealerPrefix": "CVN_" it means we take addressId for example 111111 and combine with dealer prefix and it become CVN_111111 and this dealer code we validate via /api/cache/lookupdealers

for CarMax if addressId length is 6 - we take only first 4 letters (123456 - we take 1234) and add dealer prefix CMX_

If ShipTo->addressID is empty or dealer not found via /api/cache/lookupdealers :

Send response with PunchOutSetupResponse->StartPage URL set to /punchout/portal

Customer is redirected to this page to select an address

After submission, create a customer with the selected dealer code and redirect to home page in PunchOut mode

Dealer codes are retrieved from /api/cache/lookupcommondealers

The dealer code for the request comes from /api/cache/getpunchoutpartners (corpAddressId field)

If ShipTo->addressID is valid:

Create a customer directly

Send response with PunchOutSetupResponse->StartPage URL set to /punchout/shopping/start

Customer is redirected directly to home page in PunchOut mode

Testing Endpoints

Pre-PunchOut Setup: /punchout/setup/item

can be used via browser at this point directly - http://qa2-now.tirehubonline.com/punchout/setup/item?dealerCode=111111&partnerIdentity=test&itemId=test&quantityNeeded=1

cXML Processing: /punchout/setup/request

can be used from tool or from postman (but you will not be redirected to next step)

Address Selection (if needed): /punchout/portal

http://qa2-now.tirehubonline.com/punchout/portal?cookie=99df85a3c958a552b26ba6dc04a9242f

can be used from browser directly with BuyerCookie value if you made cXML request for previous step

Shopping Start: /punchout/shopping/start

http://qa2-now.tirehubonline.com/punchout/portal?cookie=99df85a3c958a552b26ba6dc04a9242f

can be used from browser directly with BuyerCookie value if you made cXML request for previous step

Test Scenarios

1. Basic PunchOut Setup Request

Objective: Verify that the system correctly processes a valid PunchOutSetupRequest and returns a valid response.

Steps:

Send a valid cXML PunchOutSetupRequest to /punchout/setup/request

Verify the response has a 200 status code

Verify the response contains a valid PunchOutSetupResponse with a URL

Access the URL and verify you land on the store in a PunchOut session

Expected Result:

XML response contains a valid StartPage URL

The URL loads the store in a PunchOut session

Sample Request:



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
            <Credential domain="NetworkID">
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
2. Pre-PunchOut Item Setup

Objective: Verify that the system correctly handles pre-PunchOut item setup.

Steps:

Send a request to /punchout/setup/item with required parameters:

dealerCode (required)

partnerIdentity (required)

itemId (optional)

quantityNeeded (optional)

Verify the system successfully saves the item information

Initiate PunchOut flow and verify pre-saved items appear in the cart

Expected Result:

Item information is successfully saved

When the user enters the PunchOut session, the pre-saved items are already in their cart

3. Address Selection Flow

Objective: Verify that the system correctly handles the address selection process when no addressID is provided.

Steps:

Send a valid cXML PunchOutSetupRequest to /punchout/setup/request without a ShipTo addressID

Verify the response directs to /punchout/portal

Select an address from the options presented

Verify the system creates a customer with the selected dealer code

Verify redirection to the home page in PunchOut mode

Expected Result:

User is directed to the address selection page

After address selection, a customer is created

User is redirected to the shopping experience

4. Direct Shopping Flow

Objective: Verify that the system correctly handles direct shopping when a valid addressID is provided.

Steps:

Send a valid cXML PunchOutSetupRequest to /punchout/setup/request with a valid ShipTo addressID

Verify the response directs to /punchout/shopping/start

Verify the system creates a customer with the provided information

Verify redirection to the home page in PunchOut mode

Expected Result:

Customer is created directly without address selection

User is redirected directly to the shopping experience

5. Partner Validation

Objective: Verify that the system correctly validates PunchOut partners against the API.

Steps:

Query /api/cache/getpunchoutpartners to retrieve authorized partners

Send a cXML request with credentials matching an authorized partner

Send another cXML request with credentials NOT matching any authorized partner

Verify the system correctly accepts or rejects the requests

Expected Result:

Request with valid partner credentials is accepted

Request with invalid partner credentials is rejected with appropriate error

6. Return Process Testing

Objective: Verify that the "Return to Procurement" process works correctly.

Steps:

Complete a valid PunchOut session setup

Add items to cart in the store

Click the "Return to Procurement System" button

Verify the cart data is correctly formatted and sent back to the procurement system using the BrowserFormPost URL

Expected Result:

Cart data is correctly formatted

User is redirected back to the procurement system via the BrowserFormPost URL

7. Error Handling

Objective: Verify that the system handles various error conditions appropriately.

Scenarios to test:

Malformed XML request

Missing required elements

Invalid partner credentials

Invalid dealer codes

System errors during processing

Expected Result:

Appropriate error responses with clear messages

No server crashes or unexpected behavior