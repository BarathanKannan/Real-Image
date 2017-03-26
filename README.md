Here are the list of APIs for the problem.

## storeDistributor
This stores a given distributor and attach the distributor the appropriate place in the distributor's tree
#### Parameters
- name - a comma separated list of names in their proper hierarchy. (Required)
#### Usage
- [URL]/storeDistributor?name=barathan
- [URL]/storeDistributor?name=barathan,navin
- [URL]/storeDistributor?name=barathan,navin,kavin
- [URL]/storeDistributor?name=barathan,john

#### Drawbacks
- Need to give the hierarchy, yet could have done this if it is actually from UI as the we can't deviate.
- Don't look for the duplicates in the names
- Can only one root or top level distributor

#### JSON Tree structure
```
{
  "barathan": {
    "navin": {
      "kavin": 1
    },
    "john": 1
  }
}
```
## includePermissions
This includes the permission for the given distributors. Handles the hierarchy before inclusion. That is a distributor can include the permission for a location only if the parent has the permission for the location (should not be excluded in parent).
#### Parameters
- name - name of the distributor to whom we are going t include permissions (required)
- ancestors - hierarchy of distributors (parent distributor is enough though) (required except for top distributor)
- values - a comma separated locations like INDIA-GOA-PANAJI (required)
#### Usage
- [URL]/includePermissions?name=barathan&values=INDIA,US
- [URL]/includePermissions?name=vignesh&ancestors=barathan&values=US-MA,FRANCE-PARIS,US
#### Drawbacks
- Currently don't check the given location is a valid one as per the list of cities given in the problem.
- If the includes or excludes list of the parent changes this can't  handle.
#### JSON Tree Structure
```
{
  "barathan": {
    "includes": {
      "INDIA": 1,
      "US": 1
    },
    "excludes": null
  },
  "vignesh": {
    "includes": {
      "US": 1
    }
  }
}
```

## excludePermission
This excludes the permission of a distributor. For this the given location must be in the include permission for him. For instance, if the distributor doesn't have access to a state, he neither can't exclude the state or any city in the state. Can only exclude if it is included.
#### Parameters
- name - name of the distributor (required)
- values - a comma separated list of locations (required)

#### Usage
- [URL]/excludePermission?name=barathan&values=INDIA-PUNJAB,INDIA-JHARKHAND-RANCHI
	
#### JSON Tree Structure
```
{
  "barathan": {
    "includes": {
      "INDIA": 1,
      "US": 1
    },
    "excludes": {
      "INDIA": {
        "PUNJAB": 1,
        "JHARKHAND": {
          "RANCHI": 1
        }
      }
    }
  },
  "vignesh": {
    "includes": {
      "US": 1
    }
  }
}
```
## Other helper APIs
#### getDistributorsJSON 
	Gives the JSON tree of the distributors
	[URL]/getDistributorsJSON
#### getCountries
	Gets the list of countries from the given cities.csv
	[URL]/getCountries
#### getProvinces
	Gives the list of provinces for the given country
	[URL]/getProvinces?country=IN
#### getCities
	Gives the list of cities for the given province and city
	[URL]/getCities?country=IN&province=TN
#### removeAllDistributors
	Removes all the distributors stored
	[URL]/removeAllDistributors
#### removeAllPermissions
	Removes all the permissions stored
	[URL]/removeAllPermissions
#### getAllPermissions
	Gives all the permissions of  all the distributors
	[URL]/getAllPermissions
