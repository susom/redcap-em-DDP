##DDP

The DDP class is a helper EM for accessing STARR data.

There are 2 services that are supported: 

    * DDP_metadata service
    * DDP_data service.

The URL of these services is set in the Control Center.

## MetaData Service
The metadata service provides a list of fields availabile to the project which can be retrieved
from the STARR data lake. The list of fields is based on the the approval status from privacy and their IRB.
The minimum that is needed to use DDP is for the use of MRNs to be approved. If MRNs are not approved, DDP
cannot be used. Currently the supported fields include Demographics, partial list of Labs, Procedure Codes (CPT), 
Medications, ICD9 and ICD10 codes.

## Data Service
The data service will receive a list of fields to be retrieved from the STARR data lake. The fields may be temporal (changing over time) or non-temporal (i.e. demographics).

If the field is a temporal field, a min and max timestamp will be sent with the field name. For each DDP Redcap project, only one timeframe offset can be set for the entire project. This timeframe is specified as +/- 15 minutes to +/- 365 days. Each data field can have its own base timestamp.

##Setup
DDP must be setup by a Redcap SuperUser.

```$xslt


```