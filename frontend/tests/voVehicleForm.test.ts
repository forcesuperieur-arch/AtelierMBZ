import { describe, expect, it } from 'vitest'

import { applyVehicleToForm, buildVoVehiclePayload, extractVehicleCategoryId, type VoVehicleFormState } from '../composables/voVehicleForm'

function makeForm(): VoVehicleFormState {
  return {
    plaque: '',
    vin: '',
    marque: '',
    modele: '',
    categorieId: '',
    typeMoto: '',
    cylindree: '',
    annee: '',
    mileage: '',
    couleur: '',
    datePremiereMiseEnCirculation: '',
  }
}

describe('voVehicleForm', () => {
  it('extracts a category id from object or iri values', () => {
    expect(extractVehicleCategoryId({ categorie: { id: 12, nom: 'Roadster' } })).toBe('12')
    expect(extractVehicleCategoryId({ categorie: '/api/motos/categories/7' })).toBe('7')
    expect(extractVehicleCategoryId({})).toBe('')
  })

  it('hydrates the form with tariff category, type and cylindree', () => {
    const form = makeForm()

    applyVehicleToForm(form, {
      plaque: 'AA-123-BB',
      vin: 'VIN12345678901234',
      marque: 'Yamaha',
      modele: 'MT-07',
      categorie: { id: 3, nom: 'Roadster' },
      typeMoto: 'moto',
      cylindree: '689',
      annee: 2024,
      mileage: 4200,
      couleur: 'Bleu',
      datePremiereMiseEnCirculation: '2024-03-10T00:00:00+00:00',
    })

    expect(form).toMatchObject({
      plaque: 'AA-123-BB',
      marque: 'Yamaha',
      modele: 'MT-07',
      categorieId: '3',
      typeMoto: 'moto',
      cylindree: '689',
      annee: '2024',
      mileage: '4200',
      couleur: 'Bleu',
      datePremiereMiseEnCirculation: '2024-03-10',
    })
  })

  it('builds a payload with category iri and numeric fields', () => {
    const payload = buildVoVehiclePayload({
      plaque: 'AA-123-BB',
      vin: 'VIN12345678901234',
      marque: 'Honda',
      modele: 'Forza',
      categorieId: '9',
      typeMoto: 'scooter',
      cylindree: '350',
      annee: '2023',
      mileage: '1500',
      couleur: 'Noir',
      datePremiereMiseEnCirculation: '2023-05-14',
    }, '/api/clients/44')

    expect(payload).toEqual({
      plaque: 'AA-123-BB',
      vin: 'VIN12345678901234',
      marque: 'Honda',
      modele: 'Forza',
      categorie: '/api/motos/categories/9',
      typeMoto: 'scooter',
      cylindree: '350',
      annee: 2023,
      mileage: 1500,
      couleur: 'Noir',
      datePremiereMiseEnCirculation: '2023-05-14',
      client: '/api/clients/44',
    })
  })
})